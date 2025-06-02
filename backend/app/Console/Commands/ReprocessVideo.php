<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ReprocessVideo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'video:reprocess {id : The video ID to reprocess}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reprocess a failed video';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $videoId = $this->argument('id');

        $video = \App\Models\Video::find($videoId);

        if (!$video) {
            $this->error("Video with ID {$videoId} not found.");
            return 1;
        }

        $this->info("Reprocessing video: {$video->title}");

        // Reset status to processing
        $video->update(['status' => 'processing']);

        // Process the video
        $videoProcessingService = new \App\Services\VideoProcessingService();
        $videoProcessingService->processVideo($video);

        $this->info("Video reprocessing completed. Check the video status.");

        return 0;
    }
}
