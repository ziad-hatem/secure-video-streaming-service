<?php

namespace Database\Seeders;

use App\Models\Video;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestVideoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first();
        
        if (!$user) {
            $this->command->error('Test user not found. Please run TestUserSeeder first.');
            return;
        }

        // Create test videos with different statuses
        $videos = [
            [
                'title' => 'Sample Video 1 - Completed',
                'description' => 'This is a completed video ready for streaming',
                'original_filename' => 'sample1.mp4',
                'status' => 'completed',
                'duration' => 180, // 3 minutes
                'file_size' => 52428800, // 50MB
                'original_path' => 'videos/sample1.mp4',
                'hls_path' => 'hls/video_1/playlist.m3u8',
                'thumbnail_path' => 'thumbnails/video_1.jpg',
                'resolutions' => ['360p', '720p', '1080p'],
            ],
            [
                'title' => 'Sample Video 2 - Completed',
                'description' => 'Another completed video for testing',
                'original_filename' => 'sample2.mp4',
                'status' => 'completed',
                'duration' => 240, // 4 minutes
                'file_size' => 78643200, // 75MB
                'original_path' => 'videos/sample2.mp4',
                'hls_path' => 'hls/video_2/playlist.m3u8',
                'thumbnail_path' => 'thumbnails/video_2.jpg',
                'resolutions' => ['360p', '720p', '1080p'],
            ],
            [
                'title' => 'Sample Video 3 - Processing',
                'description' => 'This video is currently being processed',
                'original_filename' => 'sample3.mp4',
                'status' => 'processing',
                'duration' => null,
                'file_size' => 104857600, // 100MB
                'original_path' => 'videos/sample3.mp4',
                'hls_path' => null,
                'thumbnail_path' => null,
                'resolutions' => null,
            ],
            [
                'title' => 'Sample Video 4 - Failed',
                'description' => 'This video failed to process',
                'original_filename' => 'sample4.mp4',
                'status' => 'failed',
                'duration' => null,
                'file_size' => 157286400, // 150MB
                'original_path' => 'videos/sample4.mp4',
                'hls_path' => null,
                'thumbnail_path' => null,
                'resolutions' => null,
            ],
            [
                'title' => 'Demo Video - Product Overview',
                'description' => 'A comprehensive overview of our product features and capabilities',
                'original_filename' => 'demo.mp4',
                'status' => 'completed',
                'duration' => 300, // 5 minutes
                'file_size' => 125829120, // 120MB
                'original_path' => 'videos/demo.mp4',
                'hls_path' => 'hls/video_5/playlist.m3u8',
                'thumbnail_path' => 'thumbnails/video_5.jpg',
                'resolutions' => ['360p', '720p', '1080p'],
            ],
        ];

        foreach ($videos as $videoData) {
            Video::create([
                'user_id' => $user->id,
                'title' => $videoData['title'],
                'description' => $videoData['description'],
                'original_filename' => $videoData['original_filename'],
                'status' => $videoData['status'],
                'duration' => $videoData['duration'],
                'file_size' => $videoData['file_size'],
                'original_path' => $videoData['original_path'],
                'hls_path' => $videoData['hls_path'],
                'thumbnail_path' => $videoData['thumbnail_path'],
                'resolutions' => $videoData['resolutions'],
                'created_at' => now()->subDays(rand(1, 30)),
                'updated_at' => now()->subDays(rand(0, 5)),
            ]);
        }

        $this->command->info('Test videos created successfully!');
    }
}
