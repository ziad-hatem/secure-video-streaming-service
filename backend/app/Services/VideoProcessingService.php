<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class VideoProcessingService
{
    protected string $ffmpegPath = '/opt/homebrew/bin/ffmpeg';
    protected string $ffprobePath = '/opt/homebrew/bin/ffprobe';

    protected array $resolutions = [
        '360p' => ['width' => 640, 'height' => 360, 'bitrate' => '800k'],
        '720p' => ['width' => 1280, 'height' => 720, 'bitrate' => '2500k'],
        '1080p' => ['width' => 1920, 'height' => 1080, 'bitrate' => '5000k'],
    ];

    protected array $audioTracks = [
        'audio_128k' => ['bitrate' => '128k', 'codec' => 'aac'],
        'audio_64k' => ['bitrate' => '64k', 'codec' => 'aac'],
    ];

    protected array $chunkMappings = []; // Store chunk name mappings

    public function __construct()
    {
        // Try to detect FFmpeg paths automatically
        $this->detectFFmpegPaths();
    }

    protected function detectFFmpegPaths(): void
    {
        // Common paths for different systems
        $possiblePaths = [
            '/opt/homebrew/bin/ffmpeg',  // macOS Homebrew (Apple Silicon)
            '/usr/local/bin/ffmpeg',     // macOS Homebrew (Intel)
            '/usr/bin/ffmpeg',           // Linux
            'ffmpeg',                    // System PATH
        ];

        foreach ($possiblePaths as $path) {
            if ($this->commandExists($path)) {
                $this->ffmpegPath = $path;
                $this->ffprobePath = str_replace('ffmpeg', 'ffprobe', $path);
                break;
            }
        }
    }

    protected function commandExists(string $command): bool
    {
        $process = new Process(['which', $command]);
        $process->run();
        return $process->isSuccessful();
    }

    /**
     * Generate a secure random chunk name
     */
    protected function generateSecureChunkName(string $prefix = ''): string
    {
        $randomBytes = random_bytes(16);
        $hash = hash('sha256', $randomBytes . microtime(true));
        return $prefix . substr($hash, 0, 12);
    }

    /**
     * Generate chunk mapping for a resolution
     */
    protected function generateChunkMapping(string $resolution, int $segmentCount): array
    {
        $mapping = [];
        for ($i = 0; $i < $segmentCount; $i++) {
            $originalName = sprintf('%s_%03d.ts', $resolution, $i);
            $secureName = $this->generateSecureChunkName('seg_') . '.ts';
            $mapping[$originalName] = $secureName;
        }
        return $mapping;
    }

    /**
     * Save chunk mappings to a secure file
     */
    protected function saveChunkMappings(string $outputDir, array $mappings): void
    {
        $mappingFile = $outputDir . '/.chunk_map.json';
        $encryptedMappings = base64_encode(json_encode($mappings));
        file_put_contents($mappingFile, $encryptedMappings);
        chmod($mappingFile, 0600); // Restrict file permissions
    }

    /**
     * Load chunk mappings from file
     */
    protected function loadChunkMappings(string $outputDir): array
    {
        $mappingFile = $outputDir . '/.chunk_map.json';
        if (!file_exists($mappingFile)) {
            return [];
        }

        $encryptedMappings = file_get_contents($mappingFile);
        return json_decode(base64_decode($encryptedMappings), true) ?: [];
    }

    public function processVideo(Video $video): void
    {
        try {
            $video->update(['status' => 'processing']);

            $originalPath = storage_path('app/public/' . $video->original_path);
            $outputDir = storage_path('app/public/videos/hls/' . $video->id);
            
            // Create output directory
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Get video info
            $videoInfo = $this->getVideoInfo($originalPath);
            $video->update(['duration' => $videoInfo['duration']]);

            // Generate thumbnail
            $this->generateThumbnail($originalPath, $video);

            // Process audio tracks first
            $audioFiles = [];
            foreach ($this->audioTracks as $name => $config) {
                $audioFile = $this->transcodeAudioToHLS($originalPath, $outputDir, $name, $config);
                if ($audioFile) {
                    $audioFiles[$name] = $audioFile;
                }
            }

            // Process video tracks (video only, no audio)
            $videoFiles = [];
            foreach ($this->resolutions as $name => $config) {
                $videoFile = $this->transcodeVideoToHLS($originalPath, $outputDir, $name, $config);
                if ($videoFile) {
                    $videoFiles[$name] = $videoFile;
                }
            }

            $hlsFiles = array_merge($audioFiles, $videoFiles);

            // Create master playlist
            $masterPlaylist = $this->createMasterPlaylist($hlsFiles, $outputDir);

            // Save chunk mappings for security
            $this->saveChunkMappings($outputDir, $this->chunkMappings);

            $video->update([
                'status' => 'completed',
                'hls_path' => 'videos/hls/' . $video->id . '/master.m3u8',
                'resolutions' => array_keys($hlsFiles),
                'processed_at' => now(),
            ]);

            Log::info("Video processing completed for video ID: {$video->id}");

        } catch (\Exception $e) {
            $video->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            Log::error("Video processing failed for video ID: {$video->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Re-throw to let the caller handle it
        }
    }

    protected function getVideoInfo(string $videoPath): array
    {
        $command = [
            $this->ffprobePath,
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $videoPath
        ];

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = json_decode($process->getOutput(), true);
        $duration = (float) $output['format']['duration'];

        return ['duration' => (int) $duration];
    }

    protected function generateThumbnail(string $videoPath, Video $video): void
    {
        $thumbnailPath = storage_path('app/public/videos/thumbnails/' . $video->id . '.jpg');
        $thumbnailDir = dirname($thumbnailPath);

        if (!file_exists($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true);
        }

        $command = [
            $this->ffmpegPath,
            '-i', $videoPath,
            '-ss', '00:00:01',
            '-vframes', '1',
            '-y',
            $thumbnailPath
        ];

        $process = new Process($command);
        $process->run();

        if ($process->isSuccessful()) {
            $video->update(['thumbnail_path' => 'videos/thumbnails/' . $video->id . '.jpg']);
        }
    }

    /**
     * Transcode audio-only stream to HLS
     */
    protected function transcodeAudioToHLS(string $inputPath, string $outputDir, string $audioTrack, array $config): ?string
    {
        $outputFile = $outputDir . '/' . $audioTrack . '.m3u8';
        $tempSegmentPattern = $outputDir . '/temp_' . $audioTrack . '_%03d.ts';

        $command = [
            $this->ffmpegPath,
            '-i', $inputPath,
            '-vn', // No video
            '-c:a', $config['codec'],
            '-b:a', $config['bitrate'],
            '-hls_time', '10',
            '-hls_list_size', '0',
            '-hls_segment_filename', $tempSegmentPattern,
            '-y',
            $outputFile
        ];

        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run();

        if ($process->isSuccessful()) {
            $this->secureSegmentNames($outputDir, $audioTrack);
            return $audioTrack . '.m3u8';
        }

        Log::error("Failed to transcode audio to {$audioTrack}", [
            'error' => $process->getErrorOutput()
        ]);

        return null;
    }

    /**
     * Transcode video-only stream to HLS
     */
    protected function transcodeVideoToHLS(string $inputPath, string $outputDir, string $resolution, array $config): ?string
    {
        $outputFile = $outputDir . '/' . $resolution . '.m3u8';
        $tempSegmentPattern = $outputDir . '/temp_' . $resolution . '_%03d.ts';

        $command = [
            $this->ffmpegPath,
            '-i', $inputPath,
            '-an', // No audio
            '-c:v', 'libx264',
            '-b:v', $config['bitrate'],
            '-vf', "scale={$config['width']}:{$config['height']}",
            '-hls_time', '10',
            '-hls_list_size', '0',
            '-hls_segment_filename', $tempSegmentPattern,
            '-y',
            $outputFile
        ];

        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run();

        if ($process->isSuccessful()) {
            $this->secureSegmentNames($outputDir, $resolution);
            return $resolution . '.m3u8';
        }

        Log::error("Failed to transcode video to {$resolution}", [
            'error' => $process->getErrorOutput()
        ]);

        return null;
    }

    /**
     * Legacy method for backward compatibility
     */
    protected function transcodeToHLS(string $inputPath, string $outputDir, string $resolution, array $config): ?string
    {
        $outputFile = $outputDir . '/' . $resolution . '.m3u8';
        $tempSegmentPattern = $outputDir . '/temp_' . $resolution . '_%03d.ts';

        // First, create segments with temporary names
        $command = [
            $this->ffmpegPath,
            '-i', $inputPath,
            '-c:v', 'libx264',
            '-c:a', 'aac',
            '-b:v', $config['bitrate'],
            '-b:a', '128k',
            '-vf', "scale={$config['width']}:{$config['height']}",
            '-hls_time', '10',
            '-hls_list_size', '0',
            '-hls_segment_filename', $tempSegmentPattern,
            '-y',
            $outputFile
        ];

        $process = new Process($command);
        $process->setTimeout(3600); // 1 hour timeout
        $process->run();

        if ($process->isSuccessful()) {
            // Rename segments to secure names and update playlist
            $this->secureSegmentNames($outputDir, $resolution);
            return $resolution . '.m3u8';
        }

        Log::error("Failed to transcode to {$resolution}", [
            'error' => $process->getErrorOutput()
        ]);

        return null;
    }

    /**
     * Rename segments to secure names and update playlist
     */
    protected function secureSegmentNames(string $outputDir, string $resolution): void
    {
        $playlistFile = $outputDir . '/' . $resolution . '.m3u8';

        if (!file_exists($playlistFile)) {
            return;
        }

        // Read the original playlist
        $playlistContent = file_get_contents($playlistFile);

        // Find all temporary segment files
        $tempPattern = $outputDir . '/temp_' . $resolution . '_*.ts';
        $tempFiles = glob($tempPattern);

        if (empty($tempFiles)) {
            return;
        }

        // Generate secure mappings
        $segmentCount = count($tempFiles);
        $mapping = $this->generateChunkMapping($resolution, $segmentCount);

        // Rename files and update playlist
        $updatedPlaylist = $playlistContent;

        foreach ($tempFiles as $tempFile) {
            $tempBasename = basename($tempFile);
            $segmentIndex = (int) preg_replace('/temp_' . $resolution . '_(\d+)\.ts/', '$1', $tempBasename);
            $originalName = sprintf('%s_%03d.ts', $resolution, $segmentIndex);

            if (isset($mapping[$originalName])) {
                $secureName = $mapping[$originalName];
                $secureFile = $outputDir . '/' . $secureName;

                // Rename the file
                rename($tempFile, $secureFile);

                // Update playlist content
                $updatedPlaylist = str_replace($tempBasename, $secureName, $updatedPlaylist);
            }
        }

        // Save updated playlist
        file_put_contents($playlistFile, $updatedPlaylist);

        // Store mapping for this resolution
        $this->chunkMappings[$resolution] = $mapping;

        // Clean up any remaining temporary files
        $remainingTempFiles = glob($outputDir . '/temp_' . $resolution . '_*.ts');
        foreach ($remainingTempFiles as $tempFile) {
            unlink($tempFile);
        }

        Log::info("Secured {$segmentCount} segments for {$resolution}");
    }

    protected function createMasterPlaylist(array $hlsFiles, string $outputDir): string
    {
        $masterPlaylistPath = $outputDir . '/master.m3u8';
        $content = "#EXTM3U\n#EXT-X-VERSION:6\n#EXT-X-INDEPENDENT-SEGMENTS\n\n";

        // Add audio tracks first
        $audioGroupId = 'audio';
        $content .= "# Audio tracks\n";
        foreach ($this->audioTracks as $audioName => $audioConfig) {
            if (isset($hlsFiles[$audioName])) {
                $bandwidth = (int) str_replace('k', '000', $audioConfig['bitrate']);
                $content .= "#EXT-X-MEDIA:TYPE=AUDIO,GROUP-ID=\"{$audioGroupId}\",NAME=\"{$audioName}\",DEFAULT=YES,AUTOSELECT=YES,URI=\"{$hlsFiles[$audioName]}\"\n";
            }
        }
        $content .= "\n";

        // Add video tracks with audio group reference
        $content .= "# Video tracks\n";
        foreach ($this->resolutions as $resolution => $config) {
            if (isset($hlsFiles[$resolution])) {
                $videoBandwidth = (int) str_replace('k', '000', $config['bitrate']);
                $audioBandwidth = 128000; // Default audio bandwidth
                $totalBandwidth = $videoBandwidth + $audioBandwidth;

                $content .= "#EXT-X-STREAM-INF:BANDWIDTH={$totalBandwidth},RESOLUTION={$config['width']}x{$config['height']},AUDIO=\"{$audioGroupId}\"\n";
                $content .= "{$hlsFiles[$resolution]}\n\n";
            }
        }

        file_put_contents($masterPlaylistPath, $content);
        return 'master.m3u8';
    }
}
