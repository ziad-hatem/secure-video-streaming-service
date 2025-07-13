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

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Video $video;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout;

    /**
     * Initialize timeout from configuration
     */
    public function __construct(Video $video)
    {
        $this->video = $video;
        $this->timeout = config('video.timeouts.job_timeout', 10800); // 3 hours default
    }



    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Ensure the video model is fresh from the database
        $this->video = $this->video->fresh();

        if (!$this->video) {
            Log::error("Video not found when processing job");
            return;
        }

        Log::info("🚀 PROCESSVIDEOJOB STARTING - Video ID: {$this->video->id}", [
            'video_title' => $this->video->title,
            'file_size' => $this->video->file_size,
            'original_path' => $this->video->original_path,
            'job_class' => 'ProcessVideoJob',
            'timestamp' => now()->toISOString()
        ]);

        try {
            // Use VideoProcessingService to process the video
            $videoProcessingService = new VideoProcessingService();
            $videoProcessingService->processVideo($this->video);

            Log::info("✅ PROCESSVIDEOJOB COMPLETED - Video ID: {$this->video->id}", [
                'job_class' => 'ProcessVideoJob',
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error("Video processing job failed for video ID: {$this->video->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            // VideoProcessingService already handles status updates, but ensure it's marked as failed
            $this->video->refresh();
            if ($this->video->status !== 'failed') {
                $this->video->update([
                    'status' => 'failed',
                    'error_message' => 'Failed to process video: ' . $e->getMessage()
                ]);
            }

            // Re-throw the exception to mark the job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Ensure we have a valid video instance
        if (!$this->video || !$this->video->exists) {
            Log::error("Video processing job permanently failed but video instance is invalid", [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]);
            return;
        }

        Log::error("Video processing job permanently failed for video ID: {$this->video->id}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Ensure the video is marked as failed
        $this->video->update([
            'status' => 'failed',
            'error_message' => 'Video processing failed after multiple attempts: ' . $exception->getMessage()
        ]);
    }
}
