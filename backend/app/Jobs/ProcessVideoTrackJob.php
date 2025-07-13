<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\VideoProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessVideoTrackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Video $video;
    public string $trackName;
    public string $trackType; // 'audio' or 'video'
    public array $config;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 7200; // 2 hours per track

    /**
     * Create a new job instance.
     */
    public function __construct(Video $video, string $trackName, string $trackType, array $config)
    {
        $this->video = $video;
        $this->trackName = $trackName;
        $this->trackType = $trackType;
        $this->config = $config;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Ensure the video model is fresh from the database
        $this->video = $this->video->fresh();

        if (!$this->video) {
            Log::error("Video not found when processing track job");
            return;
        }

        Log::info("🚀 Processing {$this->trackType} track: {$this->trackName}", [
            'video_id' => $this->video->id,
            'track_name' => $this->trackName,
            'track_type' => $this->trackType
        ]);

        try {
            $videoProcessingService = new VideoProcessingService();
            $originalPath = storage_path('app/public/' . $this->video->original_path);
            $outputDir = storage_path('app/public/videos/hls/' . $this->video->id);

            // Create output directory if it doesn't exist
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $success = false;
            if ($this->trackType === 'audio') {
                $result = $videoProcessingService->transcodeAudioToHLS(
                    $originalPath, 
                    $outputDir, 
                    $this->trackName, 
                    $this->config
                );
                $success = $result !== null;
            } else {
                $result = $videoProcessingService->transcodeVideoToHLS(
                    $originalPath, 
                    $outputDir, 
                    $this->trackName, 
                    $this->config
                );
                $success = $result !== null;
            }

            if ($success) {
                Log::info("✅ {$this->trackType} track completed: {$this->trackName}", [
                    'video_id' => $this->video->id
                ]);
            } else {
                throw new \Exception("Failed to process {$this->trackType} track: {$this->trackName}");
            }

        } catch (\Exception $e) {
            Log::error("❌ {$this->trackType} track processing failed: {$this->trackName}", [
                'video_id' => $this->video->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw the exception to mark the job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("❌ {$this->trackType} track job permanently failed: {$this->trackName}", [
            'video_id' => $this->video->id ?? 'unknown',
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Mark the video as failed if this is a critical track
        if ($this->video && $this->video->exists) {
            $this->video->update([
                'status' => 'failed',
                'error_message' => "Failed to process {$this->trackType} track {$this->trackName}: " . $exception->getMessage()
            ]);
        }
    }
}
