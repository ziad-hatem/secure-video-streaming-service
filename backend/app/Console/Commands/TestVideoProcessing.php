<?php

namespace App\Console\Commands;

use App\Services\VideoProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestVideoProcessing extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'video:test-performance';

    /**
     * The console command description.
     */
    protected $description = 'Test video processing performance and hardware acceleration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Testing Video Processing Performance');
        $this->newLine();

        try {
            $service = new VideoProcessingService();
            
            // Test hardware acceleration detection
            $this->info('Hardware Acceleration Detection:');
            $reflection = new \ReflectionClass($service);
            $hwAccelProperty = $reflection->getProperty('hwAccel');
            $hwAccelProperty->setAccessible(true);
            $hwAccel = $hwAccelProperty->getValue($service);
            
            if ($hwAccel) {
                $this->info("✅ Hardware acceleration detected: {$hwAccel}");
            } else {
                $this->warn("⚠️  No hardware acceleration detected - using software encoding");
            }
            
            // Test configuration loading
            $this->newLine();
            $this->info('Configuration Settings:');
            
            $performanceProperty = $reflection->getProperty('performanceSettings');
            $performanceProperty->setAccessible(true);
            $performance = $performanceProperty->getValue($service);
            
            $this->table(['Setting', 'Value'], [
                ['Preset', $performance['preset']],
                ['Tune', $performance['tune']],
                ['Threads', $performance['threads'] ?: 'Auto-detect'],
                ['CRF', $performance['crf']],
                ['Segment Time', $performance['segment_time'] . 's'],
                ['Parallel Jobs', $performance['parallel_jobs']],
            ]);
            
            // Test resolutions
            $this->newLine();
            $this->info('Video Resolutions:');
            
            $resolutionsProperty = $reflection->getProperty('resolutions');
            $resolutionsProperty->setAccessible(true);
            $resolutions = $resolutionsProperty->getValue($service);
            
            $resolutionData = [];
            foreach ($resolutions as $name => $config) {
                $resolutionData[] = [
                    $name,
                    "{$config['width']}x{$config['height']}",
                    $config['bitrate']
                ];
            }
            
            $this->table(['Resolution', 'Dimensions', 'Bitrate'], $resolutionData);
            
            // Test timeouts
            $this->newLine();
            $this->info('Timeout Settings:');
            $this->table(['Setting', 'Value'], [
                ['Job Timeout', config('video.timeouts.job_timeout', 10800) . 's (' . gmdate('H:i:s', config('video.timeouts.job_timeout', 10800)) . ')'],
                ['Process Timeout', config('video.timeouts.process_timeout', 7200) . 's (' . gmdate('H:i:s', config('video.timeouts.process_timeout', 7200)) . ')'],
                ['Queue Retry', config('video.timeouts.queue_retry_after', 10800) . 's (' . gmdate('H:i:s', config('video.timeouts.queue_retry_after', 10800)) . ')'],
            ]);
            
            $this->newLine();
            $this->info('✅ Video processing service initialized successfully!');
            
            // Performance recommendations
            $this->newLine();
            $this->info('💡 Performance Recommendations:');
            
            if ($performance['preset'] === 'ultrafast') {
                $this->info('• Using ultrafast preset - optimized for speed');
            } else {
                $this->warn('• Consider using "ultrafast" preset for faster processing');
            }
            
            if ($performance['parallel_jobs'] >= 3) {
                $this->info('• Parallel processing enabled with ' . $performance['parallel_jobs'] . ' jobs');
            } else {
                $this->warn('• Consider increasing parallel_jobs for faster processing');
            }
            
            if ($hwAccel) {
                $this->info('• Hardware acceleration will significantly speed up encoding');
            } else {
                $this->warn('• Install GPU drivers or use a system with hardware encoding support');
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Error testing video processing: ' . $e->getMessage());
            Log::error('Video processing test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
