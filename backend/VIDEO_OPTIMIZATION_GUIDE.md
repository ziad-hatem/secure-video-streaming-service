# Video Processing Optimization Guide

## 🚀 Performance Improvements Applied

This guide documents the major optimizations implemented to significantly speed up video processing for large files (1GB+).

### 1. FFmpeg Performance Optimizations

#### Hardware Acceleration
- **Auto-detection** of available hardware encoders:
  - NVIDIA NVENC (GPU encoding)
  - Apple VideoToolbox (macOS)
  - Intel Quick Sync Video (QSV)
- **Fallback** to optimized software encoding if no hardware acceleration available

#### Encoding Parameters
- **Preset**: `ultrafast` (fastest encoding speed)
- **Tune**: `zerolatency` (optimized for speed)
- **Threads**: `0` (auto-detect all CPU cores)
- **CRF**: `28` (balanced quality/speed - higher = faster)
- **Segment Time**: `6s` (shorter segments = faster processing)

#### Bitrate Optimization
- **360p**: 600k (reduced from 800k)
- **720p**: 1800k (reduced from 2500k)  
- **1080p**: 3500k (reduced from 5000k)

### 2. Parallel Processing

#### Concurrent Track Processing
- **Audio tracks** processed in parallel
- **Video resolutions** processed simultaneously (limited by `parallel_jobs` setting)
- **Non-blocking processes** using `Process::start()` instead of `Process::run()`

#### Smart Resource Management
- Limited parallel video jobs to prevent system overload
- Remaining tracks processed sequentially if parallel limit exceeded
- Memory-efficient process handling

### 3. Extended Timeouts

#### Job Timeouts
- **Video Job**: 3 hours (increased from 1 hour)
- **FFmpeg Process**: 2 hours per track
- **Queue Retry**: 3 hours

#### Configuration-Based
- All timeouts configurable via `config/video.php`
- Environment variable overrides available

### 4. Configuration System

#### Centralized Settings (`config/video.php`)
```php
'performance' => [
    'preset' => 'ultrafast',
    'tune' => 'zerolatency', 
    'threads' => 0,
    'crf' => 28,
    'segment_time' => 6,
    'parallel_jobs' => 3,
]
```

#### Environment Variables (`.env`)
```bash
VIDEO_PRESET=ultrafast
VIDEO_PARALLEL_JOBS=3
VIDEO_JOB_TIMEOUT=10800
```

## 📊 Expected Performance Improvements

### Before Optimization
- **1GB Video**: 2-4 hours processing time
- **Sequential processing**: One track at a time
- **Conservative settings**: Higher quality, slower encoding
- **Limited timeouts**: 1 hour job timeout

### After Optimization  
- **1GB Video**: 15-30 minutes processing time (with hardware acceleration)
- **Parallel processing**: Multiple tracks simultaneously
- **Speed-optimized settings**: Faster encoding with acceptable quality
- **Extended timeouts**: 3 hour job timeout for large files

### Performance Factors
- **Hardware acceleration**: 3-5x speed improvement
- **Parallel processing**: 2-3x speed improvement  
- **Optimized settings**: 1.5-2x speed improvement
- **Combined**: Up to 10x faster processing

## 🛠️ Usage Instructions

### 1. Test Your Setup
```bash
php artisan video:test-performance
```

### 2. Configure for Your Hardware
Copy settings from `.env.video.example` to your `.env` file and adjust:

#### For Maximum Speed
```bash
VIDEO_PRESET=ultrafast
VIDEO_CRF=30
VIDEO_PARALLEL_JOBS=4
```

#### For Balanced Quality/Speed
```bash
VIDEO_PRESET=fast
VIDEO_CRF=25
VIDEO_PARALLEL_JOBS=3
```

#### For Best Quality
```bash
VIDEO_PRESET=slow
VIDEO_CRF=20
VIDEO_PARALLEL_JOBS=2
```

### 3. Monitor Processing
Check logs for performance information:
```bash
tail -f storage/logs/laravel.log | grep "video processing\|transcoding"
```

## 🔧 Troubleshooting

### Slow Processing
1. **Check hardware acceleration**: Run `php artisan video:test-performance`
2. **Increase parallel jobs**: Set `VIDEO_PARALLEL_JOBS=4` (if you have enough CPU/memory)
3. **Use faster preset**: Set `VIDEO_PRESET=ultrafast`
4. **Lower quality**: Increase `VIDEO_CRF=30`

### Memory Issues
1. **Reduce parallel jobs**: Set `VIDEO_PARALLEL_JOBS=2`
2. **Increase PHP memory**: Set `memory_limit=2048M` in php.ini
3. **Monitor system resources**: Use `htop` or Activity Monitor

### Timeout Errors
1. **Increase timeouts**: Adjust `VIDEO_JOB_TIMEOUT` and `VIDEO_PROCESS_TIMEOUT`
2. **Check queue worker**: Ensure `php artisan queue:work` is running
3. **Monitor job status**: Check `jobs` table in database

## 📈 Monitoring & Metrics

### Key Metrics to Track
- **Processing time per GB**
- **Hardware acceleration usage**
- **Parallel job efficiency**
- **Memory usage during processing**
- **Queue job success rate**

### Log Messages to Watch
- `🚀 Starting optimized video processing`
- `✅ Video processing completed successfully`
- `Hardware acceleration: [type] detected`
- `🎵 Started audio transcoding` / `🎬 Started video transcoding`

## 🔄 Future Optimizations

### Potential Improvements
1. **Progress tracking**: Real-time processing progress
2. **Adaptive quality**: Adjust settings based on source video
3. **Chunk-based processing**: Process very large files in chunks
4. **Distributed processing**: Multiple worker servers
5. **GPU memory optimization**: Better GPU resource management

### Configuration Tuning
- Monitor actual processing times and adjust settings
- Test different presets for your specific hardware
- Optimize parallel job count based on CPU cores and memory
- Fine-tune bitrates for your quality requirements
