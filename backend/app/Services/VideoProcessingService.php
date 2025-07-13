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

    // Configuration loaded from config/video.php
    protected array $resolutions;
    protected array $audioTracks;
    protected array $performanceSettings;

    // Hardware acceleration detection
    protected ?string $hwAccel = null;

    protected array $chunkMappings = []; // Store chunk name mappings
    protected array $encryptionKeys = []; // Store encryption keys for each resolution

    public function __construct()
    {
        $this->chunkMappings = [];
        $this->encryptionKeys = [];

        // Load configuration
        $this->loadConfiguration();

        // Try to detect FFmpeg paths automatically
        $this->detectFFmpegPaths();

        // Detect hardware acceleration
        $this->detectHardwareAcceleration();
    }

    /**
     * Load configuration from config/video.php
     */
    protected function loadConfiguration(): void
    {
        $this->resolutions = config('video.resolutions', [
            '360p' => ['width' => 640, 'height' => 360, 'bitrate' => '600k'],
            '720p' => ['width' => 1280, 'height' => 720, 'bitrate' => '1800k'],
            '1080p' => ['width' => 1920, 'height' => 1080, 'bitrate' => '3500k'],
        ]);

        $this->audioTracks = config('video.audio', [
            'audio_128k' => ['bitrate' => '128k', 'codec' => 'aac'],
            'audio_64k' => ['bitrate' => '64k', 'codec' => 'aac'],
        ]);

        $this->performanceSettings = config('video.performance', [
            'preset' => 'ultrafast',
            'tune' => 'zerolatency',
            'threads' => 0,
            'crf' => 28,
            'segment_time' => 6,
            'parallel_jobs' => 3,
        ]);
    }

    protected function detectFFmpegPaths(): void
    {
        // Use configured paths if available
        $configuredFFmpeg = config('video.paths.ffmpeg');
        $configuredFFprobe = config('video.paths.ffprobe');

        if ($configuredFFmpeg && $this->commandExists($configuredFFmpeg)) {
            $this->ffmpegPath = $configuredFFmpeg;
        }

        if ($configuredFFprobe && $this->commandExists($configuredFFprobe)) {
            $this->ffprobePath = $configuredFFprobe;
            return;
        }

        // Auto-detect if not configured
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
     * Detect available hardware acceleration
     */
    protected function detectHardwareAcceleration(): void
    {
        // Check for NVIDIA GPU (NVENC)
        if ($this->checkHardwareEncoder('h264_nvenc')) {
            $this->hwAccel = 'nvenc';
            Log::info('Hardware acceleration: NVIDIA NVENC detected');
            return;
        }

        // Check for macOS VideoToolbox
        if ($this->checkHardwareEncoder('h264_videotoolbox')) {
            $this->hwAccel = 'videotoolbox';
            Log::info('Hardware acceleration: VideoToolbox detected');
            return;
        }

        // Check for Intel Quick Sync
        if ($this->checkHardwareEncoder('h264_qsv')) {
            $this->hwAccel = 'qsv';
            Log::info('Hardware acceleration: Intel Quick Sync detected');
            return;
        }

        Log::info('Hardware acceleration: Using software encoding (CPU only)');
    }

    /**
     * Check if a hardware encoder is available
     */
    protected function checkHardwareEncoder(string $encoder): bool
    {
        $command = [$this->ffmpegPath, '-hide_banner', '-encoders'];
        $process = new Process($command);
        $process->run();

        if ($process->isSuccessful()) {
            return str_contains($process->getOutput(), $encoder);
        }

        return false;
    }

    /**
     * Get optimized encoding parameters based on hardware acceleration
     */
    protected function getOptimizedEncodingParams(): array
    {
        $params = [
            '-preset', $this->performanceSettings['preset'],
            '-tune', $this->performanceSettings['tune'],
            '-threads', (string) $this->performanceSettings['threads'],
            '-crf', (string) $this->performanceSettings['crf'],
        ];

        // Add hardware acceleration if available
        switch ($this->hwAccel) {
            case 'nvenc':
                $params = array_merge(['-c:v', 'h264_nvenc', '-preset', 'fast'], $params);
                break;
            case 'videotoolbox':
                $params = array_merge(['-c:v', 'h264_videotoolbox', '-realtime', '1'], $params);
                break;
            case 'qsv':
                $params = array_merge(['-c:v', 'h264_qsv', '-preset', 'fast'], $params);
                break;
            default:
                $params = array_merge(['-c:v', 'libx264'], $params);
        }

        return $params;
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
     * Generate encryption key for a resolution
     */
    protected function generateEncryptionKey(string $resolution): string
    {
        return bin2hex(random_bytes(16)); // 128-bit key
    }

    /**
     * Generate initialization vector for encryption
     */
    protected function generateIV(): string
    {
        return bin2hex(random_bytes(16)); // 128-bit IV
    }

    /**
     * Save encryption key to file
     */
    protected function saveEncryptionKey(string $outputDir, string $resolution, string $key): string
    {
        $keyFileName = $this->generateSecureChunkName('key_') . '.key';
        $keyFilePath = $outputDir . '/' . $keyFileName;

        // Write the key in binary format
        file_put_contents($keyFilePath, hex2bin($key));
        chmod($keyFilePath, 0600); // Restrict file permissions

        return $keyFileName;
    }

    /**
     * Create key info file for FFmpeg encryption
     */
    protected function createKeyInfoFile(string $outputDir, string $resolution, string $keyFileName, string $key, string $iv): string
    {
        $keyInfoFileName = $outputDir . '/keyinfo_' . $resolution . '.txt';

        // Key info file format for FFmpeg:
        // Line 1: Key URI (how the player will request the key)
        // Line 2: Path to key file (for FFmpeg to read during encoding)
        // Line 3: IV in hex format
        // Use relative URL for key endpoint (works with any port/domain)
        $keyUri = '/api/hls/key/' . $keyFileName;
        $keyFilePath = $outputDir . '/' . $keyFileName;

        $keyInfoContent = $keyUri . "\n" . $keyFilePath . "\n" . $iv;
        file_put_contents($keyInfoFileName, $keyInfoContent);

        return $keyInfoFileName;
    }

    /**
     * Clean up temporary files after processing
     */
    protected function cleanupTempFiles(string $outputDir): void
    {
        // Remove key info files
        $keyInfoFiles = glob($outputDir . '/keyinfo_*.txt');
        foreach ($keyInfoFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        // Remove any remaining temporary segment files
        $tempFiles = glob($outputDir . '/temp_*.ts');
        foreach ($tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Generate chunk mapping for a resolution or audio track
     */
    protected function generateChunkMapping(string $trackName, int $segmentCount, string $type = 'video'): array
    {
        $mapping = [];
        $prefix = $type === 'audio' ? 'aud_' : 'seg_';

        for ($i = 0; $i < $segmentCount; $i++) {
            $originalName = sprintf('temp_%s_%03d.ts', $trackName, $i);
            $secureName = $this->generateSecureChunkName($prefix) . '.ts';
            $mapping[$originalName] = $secureName;
        }
        return $mapping;
    }

    /**
     * Save chunk mappings and encryption keys to secure files
     */
    protected function saveChunkMappings(string $outputDir, array $mappings): void
    {
        // Save chunk mappings
        $mappingFile = $outputDir . '/.chunk_map.json';
        $encryptedMappings = base64_encode(json_encode($mappings));
        file_put_contents($mappingFile, $encryptedMappings);
        chmod($mappingFile, 0600); // Restrict file permissions

        // Save encryption keys mapping
        $encryptionFile = $outputDir . '/.encryption_map.json';
        $encryptedKeys = base64_encode(json_encode($this->encryptionKeys));
        file_put_contents($encryptionFile, $encryptedKeys);
        chmod($encryptionFile, 0600); // Restrict file permissions
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

            Log::info("🚀 Starting optimized video processing", [
                'video_id' => $video->id,
                'duration' => $videoInfo['duration'],
                'hw_accel' => $this->hwAccel ?? 'software',
                'parallel_jobs' => $this->performanceSettings['parallel_jobs']
            ]);

            // Generate thumbnail quickly
            $this->generateThumbnail($originalPath, $video);

            // Process tracks in parallel for better performance
            $hlsFiles = $this->processTracksInParallel($originalPath, $outputDir);

            // Create master playlist
            $masterPlaylist = $this->createMasterPlaylist($hlsFiles, $outputDir);

            // Save chunk mappings and encryption keys for security
            $this->saveChunkMappings($outputDir, $this->chunkMappings);

            // Clean up temporary key info files
            $this->cleanupTempFiles($outputDir);

            $video->update([
                'status' => 'completed',
                'hls_path' => 'videos/hls/' . $video->id . '/master.m3u8',
                'resolutions' => array_keys($hlsFiles),
                'processed_at' => now(),
            ]);

            Log::info("✅ Video processing completed successfully", [
                'video_id' => $video->id,
                'tracks_processed' => count($hlsFiles)
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

    /**
     * Process audio and video tracks in parallel for better performance
     */
    protected function processTracksInParallel(string $inputPath, string $outputDir): array
    {
        $processes = [];
        $hlsFiles = [];

        // Start audio processing (lightweight, process first)
        foreach ($this->audioTracks as $name => $config) {
            $processes[] = [
                'type' => 'audio',
                'name' => $name,
                'config' => $config,
                'process' => $this->startAudioTranscoding($inputPath, $outputDir, $name, $config)
            ];
        }

        // Start video processing with limited parallelism to avoid overwhelming the system
        $videoProcesses = 0;
        foreach ($this->resolutions as $name => $config) {
            if ($videoProcesses < $this->performanceSettings['parallel_jobs']) {
                $processes[] = [
                    'type' => 'video',
                    'name' => $name,
                    'config' => $config,
                    'process' => $this->startVideoTranscoding($inputPath, $outputDir, $name, $config)
                ];
                $videoProcesses++;
            }
        }

        // Wait for processes to complete and collect results
        foreach ($processes as $processInfo) {
            $process = $processInfo['process'];

            if ($process && $process->isRunning()) {
                $process->wait(); // Wait for completion
            }

            if ($process && $process->isSuccessful()) {
                $this->secureSegmentNames($outputDir, $processInfo['name']);
                $hlsFiles[$processInfo['name']] = $processInfo['name'] . '.m3u8';

                Log::info("✅ {$processInfo['type']} track completed: {$processInfo['name']}");
            } else {
                Log::error("❌ Failed to process {$processInfo['type']} track: {$processInfo['name']}", [
                    'error' => $process ? $process->getErrorOutput() : 'Process failed to start'
                ]);
            }
        }

        // Process remaining video resolutions if we had more than parallel limit
        $processedVideo = array_filter($processes, fn($p) => $p['type'] === 'video');
        $remainingVideo = array_diff_key($this->resolutions, array_column($processedVideo, 'name', 'name'));

        foreach ($remainingVideo as $name => $config) {
            $videoFile = $this->transcodeVideoToHLS($inputPath, $outputDir, $name, $config);
            if ($videoFile) {
                $hlsFiles[$name] = $videoFile;
            }
        }

        return $hlsFiles;
    }

    /**
     * Start audio transcoding process (non-blocking)
     */
    protected function startAudioTranscoding(string $inputPath, string $outputDir, string $audioTrack, array $config): ?Process
    {
        $outputFile = $outputDir . '/' . $audioTrack . '.m3u8';
        $tempSegmentPattern = $outputDir . '/temp_' . $audioTrack . '_%03d.ts';

        // Generate encryption key and IV
        $encryptionKey = $this->generateEncryptionKey($audioTrack);
        $iv = $this->generateIV();
        $keyFileName = $this->saveEncryptionKey($outputDir, $audioTrack, $encryptionKey);

        // Store encryption info for later use
        $this->encryptionKeys[$audioTrack] = [
            'key' => $encryptionKey,
            'iv' => $iv,
            'key_file' => $keyFileName
        ];

        $command = [
            $this->ffmpegPath,
            '-i', $inputPath,
            '-vn', // No video
            '-c:a', $config['codec'],
            '-b:a', $config['bitrate'],
            '-threads', (string) $this->performanceSettings['threads'],
            '-hls_time', (string) $this->performanceSettings['segment_time'],
            '-hls_list_size', '0',
            '-hls_segment_filename', $tempSegmentPattern,
            // Encryption parameters
            '-hls_key_info_file', $this->createKeyInfoFile($outputDir, $audioTrack, $keyFileName, $encryptionKey, $iv),
            '-y',
            $outputFile
        ];

        $process = new Process($command);
        $process->setTimeout(config('video.timeouts.process_timeout', 7200));
        $process->start(); // Start asynchronously

        Log::info("🎵 Started audio transcoding: {$audioTrack}");
        return $process;
    }

    /**
     * Start video transcoding process (non-blocking)
     */
    protected function startVideoTranscoding(string $inputPath, string $outputDir, string $resolution, array $config): ?Process
    {
        $outputFile = $outputDir . '/' . $resolution . '.m3u8';
        $tempSegmentPattern = $outputDir . '/temp_' . $resolution . '_%03d.ts';

        // Generate encryption key and IV
        $encryptionKey = $this->generateEncryptionKey($resolution);
        $iv = $this->generateIV();
        $keyFileName = $this->saveEncryptionKey($outputDir, $resolution, $encryptionKey);

        // Store encryption info for later use
        $this->encryptionKeys[$resolution] = [
            'key' => $encryptionKey,
            'iv' => $iv,
            'key_file' => $keyFileName
        ];

        // Get optimized encoding parameters
        $encodingParams = $this->getOptimizedEncodingParams();

        $command = array_merge([
            $this->ffmpegPath,
            '-i', $inputPath,
            '-an', // No audio
        ], $encodingParams, [
            '-b:v', $config['bitrate'],
            '-vf', "scale={$config['width']}:{$config['height']}",
            '-hls_time', (string) $this->performanceSettings['segment_time'],
            '-hls_list_size', '0',
            '-hls_segment_filename', $tempSegmentPattern,
            // Encryption parameters
            '-hls_key_info_file', $this->createKeyInfoFile($outputDir, $resolution, $keyFileName, $encryptionKey, $iv),
            '-y',
            $outputFile
        ]);

        $process = new Process($command);
        $process->setTimeout(config('video.timeouts.process_timeout', 7200));
        $process->start(); // Start asynchronously

        Log::info("🎬 Started video transcoding: {$resolution} (HW: {$this->hwAccel})");
        return $process;
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
     * Transcode audio-only stream to HLS with encryption
     */
    public function transcodeAudioToHLS(string $inputPath, string $outputDir, string $audioTrack, array $config): ?string
    {
        $outputFile = $outputDir . '/' . $audioTrack . '.m3u8';
        $tempSegmentPattern = $outputDir . '/temp_' . $audioTrack . '_%03d.ts';

        // Generate encryption key and IV
        $encryptionKey = $this->generateEncryptionKey($audioTrack);
        $iv = $this->generateIV();
        $keyFileName = $this->saveEncryptionKey($outputDir, $audioTrack, $encryptionKey);

        // Store encryption info for later use
        $this->encryptionKeys[$audioTrack] = [
            'key' => $encryptionKey,
            'iv' => $iv,
            'key_file' => $keyFileName
        ];

        $command = [
            $this->ffmpegPath,
            '-i', $inputPath,
            '-vn', // No video
            '-c:a', $config['codec'],
            '-b:a', $config['bitrate'],
            '-threads', (string) $this->performanceSettings['threads'],
            '-hls_time', (string) $this->performanceSettings['segment_time'],
            '-hls_list_size', '0',
            '-hls_segment_filename', $tempSegmentPattern,
            // Encryption parameters
            '-hls_key_info_file', $this->createKeyInfoFile($outputDir, $audioTrack, $keyFileName, $encryptionKey, $iv),
            '-y',
            $outputFile
        ];

        $process = new Process($command);
        $process->setTimeout(config('video.timeouts.process_timeout', 7200));
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
     * Transcode video-only stream to HLS with encryption
     */
    public function transcodeVideoToHLS(string $inputPath, string $outputDir, string $resolution, array $config): ?string
    {
        $outputFile = $outputDir . '/' . $resolution . '.m3u8';
        $tempSegmentPattern = $outputDir . '/temp_' . $resolution . '_%03d.ts';

        // Generate encryption key and IV
        $encryptionKey = $this->generateEncryptionKey($resolution);
        $iv = $this->generateIV();
        $keyFileName = $this->saveEncryptionKey($outputDir, $resolution, $encryptionKey);

        // Store encryption info for later use
        $this->encryptionKeys[$resolution] = [
            'key' => $encryptionKey,
            'iv' => $iv,
            'key_file' => $keyFileName
        ];

        // Get optimized encoding parameters
        $encodingParams = $this->getOptimizedEncodingParams();

        $command = array_merge([
            $this->ffmpegPath,
            '-i', $inputPath,
            '-an', // No audio
        ], $encodingParams, [
            '-b:v', $config['bitrate'],
            '-vf', "scale={$config['width']}:{$config['height']}",
            '-hls_time', (string) $this->performanceSettings['segment_time'],
            '-hls_list_size', '0',
            '-hls_segment_filename', $tempSegmentPattern,
            // Encryption parameters
            '-hls_key_info_file', $this->createKeyInfoFile($outputDir, $resolution, $keyFileName, $encryptionKey, $iv),
            '-y',
            $outputFile
        ]);

        $process = new Process($command);
        $process->setTimeout(config('video.timeouts.process_timeout', 7200));
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
    protected function secureSegmentNames(string $outputDir, string $trackName): void
    {
        $playlistFile = $outputDir . '/' . $trackName . '.m3u8';

        if (!file_exists($playlistFile)) {
            return;
        }

        // Read the original playlist
        $playlistContent = file_get_contents($playlistFile);

        // Find all temporary segment files for this track
        $tempPattern = $outputDir . '/temp_' . $trackName . '_*.ts';
        $tempFiles = glob($tempPattern);

        if (empty($tempFiles)) {
            return;
        }

        // Determine if this is an audio or video track
        $isAudioTrack = array_key_exists($trackName, $this->audioTracks);
        $trackType = $isAudioTrack ? 'audio' : 'video';

        // Generate secure mappings
        $segmentCount = count($tempFiles);
        $mapping = $this->generateChunkMapping($trackName, $segmentCount, $trackType);

        // Rename files and update playlist
        $updatedPlaylist = $playlistContent;

        foreach ($tempFiles as $tempFile) {
            $tempBasename = basename($tempFile);

            if (isset($mapping[$tempBasename])) {
                $secureName = $mapping[$tempBasename];
                $secureFile = $outputDir . '/' . $secureName;

                // Rename the file
                rename($tempFile, $secureFile);

                // Update playlist content
                $updatedPlaylist = str_replace($tempBasename, $secureName, $updatedPlaylist);
            }
        }

        // Save updated playlist
        file_put_contents($playlistFile, $updatedPlaylist);

        // Store mapping for this track
        $this->chunkMappings[$trackName] = $mapping;

        // Clean up any remaining temporary files
        $remainingTempFiles = glob($outputDir . '/temp_' . $trackName . '_*.ts');
        foreach ($remainingTempFiles as $tempFile) {
            unlink($tempFile);
        }

        Log::info("Secured {$segmentCount} segments for {$trackName} ({$trackType})");
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
