# Video Streaming Platform

A full-stack video streaming application with adaptive bitrate streaming built with Next.js frontend and Laravel backend.

## Features

- **Video Upload**: Upload videos with progress tracking
- **Adaptive Streaming**: HLS (HTTP Live Streaming) with multiple resolutions (360p, 720p, 1080p)
- **Video Processing**: Automatic transcoding using FFmpeg
- **Modern UI**: Responsive design with Tailwind CSS
- **Video Player**: HTML5 player with Video.js supporting adaptive bitrate switching

## Tech Stack

### Frontend
- **Next.js 15** with TypeScript
- **Tailwind CSS** for styling
- **Video.js** for video playback
- **Axios** for API communication

### Backend
- **Laravel 12** (PHP)
- **MySQL** database
- **FFmpeg** for video processing
- **HLS** streaming protocol

## Prerequisites

Before running this application, make sure you have the following installed:

1. **Node.js** (v18 or higher)
2. **PHP** (v8.2 or higher)
3. **Composer** (PHP package manager)
4. **MySQL** (v8.0 or higher)
5. **FFmpeg** (for video processing)

### Installing FFmpeg

#### macOS (using Homebrew)
```bash
brew install ffmpeg
```

#### Ubuntu/Debian
```bash
sudo apt update
sudo apt install ffmpeg
```

#### Windows
Download from [https://ffmpeg.org/download.html](https://ffmpeg.org/download.html) and add to PATH.

## Installation

### 1. Clone the Repository
```bash
git clone <repository-url>
cd video-streaming-platform
```

### 2. Backend Setup (Laravel)

```bash
cd backend

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env file
# Update these values in .env:
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=video_streaming
DB_USERNAME=root
DB_PASSWORD=your_password

# Create database
mysql -u root -p -e "CREATE DATABASE video_streaming;"

# Run migrations
php artisan migrate

# Create storage link
php artisan storage:link

# Start Laravel development server
php artisan serve
```

The Laravel backend will be available at `http://localhost:8000`

### 3. Frontend Setup (Next.js)

```bash
cd frontend

# Install Node.js dependencies
npm install

# Start Next.js development server
npm run dev
```

The Next.js frontend will be available at `http://localhost:3000`

## API Endpoints

### Video Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/videos` | Get all videos with pagination |
| POST | `/api/videos/upload` | Upload a new video |
| GET | `/api/videos/{id}` | Get specific video details |
| GET | `/api/videos/{id}/stream` | Get streaming URLs for a video |

### Upload Video Example

```bash
curl -X POST http://localhost:8000/api/videos/upload \
  -F "title=My Video" \
  -F "description=Video description" \
  -F "video=@/path/to/video.mp4"
```

### Get Stream Data Example

```bash
curl http://localhost:8000/api/videos/1/stream
```

Response:
```json
{
  "hls_url": "http://localhost:8000/storage/videos/hls/1/master.m3u8",
  "thumbnail_url": "http://localhost:8000/storage/videos/thumbnails/1.jpg",
  "resolutions": ["360p", "720p", "1080p"],
  "duration": 120
}
```

## File Structure

```
├── backend/                 # Laravel backend
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   └── VideoController.php
│   │   ├── Models/
│   │   │   └── Video.php
│   │   └── Services/
│   │       └── VideoProcessingService.php
│   ├── database/migrations/
│   ├── routes/api.php
│   └── storage/app/public/
│       └── videos/
│           ├── originals/   # Original uploaded videos
│           ├── hls/         # HLS segments and playlists
│           └── thumbnails/  # Video thumbnails
├── frontend/                # Next.js frontend
│   ├── src/
│   │   ├── app/
│   │   │   └── page.tsx
│   │   └── components/
│   │       ├── VideoUpload.tsx
│   │       ├── VideoPlayer.tsx
│   │       └── VideoList.tsx
│   └── package.json
└── README.md
```

## Video Processing Workflow

1. **Upload**: User uploads video through frontend
2. **Storage**: Original video stored in `storage/app/public/videos/originals/`
3. **Processing**: FFmpeg transcodes video into multiple resolutions
4. **Segmentation**: Videos are segmented into HLS chunks
5. **Playlist**: Master playlist created for adaptive streaming
6. **Thumbnail**: Thumbnail generated from video
7. **Completion**: Video status updated to 'completed'

## Configuration

### Video Processing Settings

Edit `backend/app/Services/VideoProcessingService.php` to modify:

- **Resolutions**: Add/remove video quality options
- **Bitrates**: Adjust encoding bitrates
- **Segment Duration**: Change HLS segment length
- **Output Formats**: Modify FFmpeg parameters

### Upload Limits

Modify upload limits in:
- `backend/app/Http/Controllers/VideoController.php` (validation rules)
- `php.ini` (upload_max_filesize, post_max_size)
- Web server configuration

## Troubleshooting

### Common Issues

1. **FFmpeg not found**
   - Ensure FFmpeg is installed and in PATH
   - Check with: `ffmpeg -version`

2. **Permission errors**
   - Set proper permissions: `chmod -R 755 storage/`
   - Ensure web server can write to storage directory

3. **Video processing fails**
   - Check Laravel logs: `tail -f storage/logs/laravel.log`
   - Verify FFmpeg can process your video format

4. **CORS errors**
   - Ensure frontend URL is in `backend/config/cors.php`
   - Check browser console for specific errors

### Performance Optimization

- Use Redis for job queues in production
- Implement CDN for video delivery
- Use dedicated video processing servers
- Enable gzip compression for HLS segments

## Production Deployment

For production deployment:

1. Use environment-specific `.env` files
2. Set up proper web server (Nginx/Apache)
3. Configure SSL certificates
4. Use job queues for video processing
5. Implement proper error handling and logging
6. Set up monitoring and alerts

## License

This project is open source and available under the [MIT License](LICENSE).
