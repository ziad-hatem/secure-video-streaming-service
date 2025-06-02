<?php

namespace App\Console\Commands;

use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ProcessVideos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'videos:process {--video-id= : Process specific video ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process uploaded videos in the background';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $videoId = $this->option('video-id');

        if ($videoId) {
            // Process specific video
            $video = Video::find($videoId);
            if (!$video) {
                $this->error("Video with ID {$videoId} not found");
                return 1;
            }
            $this->processVideo($video);
        } else {
            // Process all pending videos
            $videos = Video::whereIn('status', ['uploaded', 'processing'])->get();

            if ($videos->isEmpty()) {
                $this->info('No videos to process');
                return 0;
            }

            $this->info("Found {$videos->count()} videos to process");

            foreach ($videos as $video) {
                $this->processVideo($video);
            }
        }

        return 0;
    }

    private function processVideo(Video $video)
    {
        $this->info("Processing video: {$video->title} (ID: {$video->id})");

        try {
            // Update status to processing
            $video->update(['status' => 'processing']);

            // Simulate video processing (in real implementation, this would be actual video processing)
            $this->info("  - Analyzing video file...");
            sleep(2); // Simulate analysis time

            // Generate mock HLS path and thumbnail
            $hlsPath = "hls/video_{$video->id}/master.m3u8";
            $thumbnailPath = "thumbnails/video_{$video->id}.jpg";

            $this->info("  - Creating HLS segments...");
            sleep(3); // Simulate HLS creation time

            $this->info("  - Generating thumbnail...");
            sleep(1); // Simulate thumbnail generation time

            // Create mock HLS directory structure
            $hlsDir = "hls/video_{$video->id}";
            Storage::disk('public')->makeDirectory($hlsDir);

            // Create mock playlist file
            $playlistContent = "#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-TARGETDURATION:10\n#EXT-X-MEDIA-SEQUENCE:0\n";
            $playlistContent .= "#EXTINF:10.0,\nsegment_001.ts\n";
            $playlistContent .= "#EXTINF:10.0,\nsegment_002.ts\n";
            $playlistContent .= "#EXTINF:8.5,\nsegment_003.ts\n";
            $playlistContent .= "#EXT-X-ENDLIST\n";

            Storage::disk('public')->put($hlsPath, $playlistContent);

            // Create mock thumbnail directory
            Storage::disk('public')->makeDirectory('thumbnails');

            // Create a simple text file as mock thumbnail (in real implementation, this would be an actual image)
            Storage::disk('public')->put($thumbnailPath, 'Mock thumbnail for video ' . $video->id);

            // Update video with processing results
            $video->update([
                'status' => 'completed',
                'hls_path' => $hlsPath,
                'thumbnail_path' => $thumbnailPath,
                'duration' => 180, // Mock duration in seconds
                'resolutions' => ['360p', '720p', '1080p'], // Mock available resolutions
                'processed_at' => now(),
            ]);

            $this->info("  ✅ Video processed successfully!");

        } catch (\Exception $e) {
            $this->error("  ❌ Failed to process video: " . $e->getMessage());

            // Update status to failed
            $video->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
