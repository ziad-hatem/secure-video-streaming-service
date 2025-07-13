<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Video Processing Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for video processing,
    | including performance optimizations and encoding settings.
    |
    */

    'performance' => [
        // FFmpeg encoding preset (ultrafast, superfast, veryfast, faster, fast, medium, slow, slower, veryslow)
        'preset' => env('VIDEO_PRESET', 'ultrafast'),
        
        // FFmpeg tuning (film, animation, grain, stillimage, psnr, ssim, fastdecode, zerolatency)
        'tune' => env('VIDEO_TUNE', 'zerolatency'),
        
        // Number of threads (0 = auto-detect all cores)
        'threads' => (int) env('VIDEO_THREADS', 0),
        
        // CRF value for quality/speed balance (lower = better quality, higher = faster encoding)
        'crf' => (int) env('VIDEO_CRF', 28),
        
        // HLS segment duration in seconds (shorter = faster processing, more segments)
        'segment_time' => (int) env('VIDEO_SEGMENT_TIME', 6),
        
        // Number of parallel video encoding jobs
        'parallel_jobs' => (int) env('VIDEO_PARALLEL_JOBS', 3),
        
        // Enable hardware acceleration detection
        'hardware_acceleration' => env('VIDEO_HARDWARE_ACCELERATION', true),
    ],

    'resolutions' => [
        '360p' => [
            'width' => 640,
            'height' => 360,
            'bitrate' => env('VIDEO_BITRATE_360P', '600k'),
        ],
        '720p' => [
            'width' => 1280,
            'height' => 720,
            'bitrate' => env('VIDEO_BITRATE_720P', '1800k'),
        ],
        '1080p' => [
            'width' => 1920,
            'height' => 1080,
            'bitrate' => env('VIDEO_BITRATE_1080P', '3500k'),
        ],
    ],

    'audio' => [
        'audio_128k' => [
            'bitrate' => '128k',
            'codec' => 'aac',
        ],
        'audio_64k' => [
            'bitrate' => '64k',
            'codec' => 'aac',
        ],
    ],

    'timeouts' => [
        // Job timeout in seconds
        'job_timeout' => (int) env('VIDEO_JOB_TIMEOUT', 10800), // 3 hours
        
        // FFmpeg process timeout in seconds
        'process_timeout' => (int) env('VIDEO_PROCESS_TIMEOUT', 7200), // 2 hours
        
        // Queue retry timeout in seconds
        'queue_retry_after' => (int) env('VIDEO_QUEUE_RETRY_AFTER', 10800), // 3 hours
    ],

    'paths' => [
        // FFmpeg binary path (auto-detected if null)
        'ffmpeg' => env('FFMPEG_PATH'),
        
        // FFprobe binary path (auto-detected if null)
        'ffprobe' => env('FFPROBE_PATH'),
    ],

    'optimization' => [
        // Enable parallel processing
        'parallel_processing' => env('VIDEO_PARALLEL_PROCESSING', true),
        
        // Enable progress tracking
        'progress_tracking' => env('VIDEO_PROGRESS_TRACKING', true),
        
        // Enable detailed logging
        'detailed_logging' => env('VIDEO_DETAILED_LOGGING', true),
        
        // Cleanup temporary files after processing
        'cleanup_temp_files' => env('VIDEO_CLEANUP_TEMP_FILES', true),
    ],
];
