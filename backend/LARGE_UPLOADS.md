# Large File Upload Configuration

This document explains how to configure the system to support large video file uploads (up to 1GB).

## Quick Start

### For Development (Laravel built-in server)

1. **Use the custom startup script:**
   ```bash
   cd backend
   ./start-server.sh
   ```

   This script starts the Laravel server with a custom PHP configuration that supports 1GB uploads.

### For Production

You'll need to configure your web server and PHP settings.

## Configuration Details

### PHP Settings Required

The following PHP settings need to be configured to support 1GB uploads:

```ini
; File upload settings
upload_max_filesize = 1024M
post_max_size = 1024M
max_file_uploads = 20

; Memory and execution time
memory_limit = 1024M
max_execution_time = 300
max_input_time = 300

; Enable file uploads
file_uploads = On
```

### Laravel Configuration

The Laravel application is already configured to handle 1GB uploads:

- **Validation Rule**: `max:1048576` (1GB in KB)
- **Upload Timeout**: 10 minutes for frontend uploads
- **Error Handling**: Detailed error messages for upload failures

### Frontend Configuration

The frontend is configured with:

- **File Size Validation**: 1GB maximum
- **Upload Timeout**: 10 minutes
- **Progress Tracking**: Real-time upload progress
- **Error Handling**: User-friendly error messages

## Production Setup

### Apache Configuration

Add to your virtual host or `.htaccess`:

```apache
# Increase upload limits
php_value upload_max_filesize 1024M
php_value post_max_size 1024M
php_value memory_limit 1024M
php_value max_execution_time 300
php_value max_input_time 300
```

### Nginx Configuration

Add to your server block:

```nginx
# Increase client body size for uploads
client_max_body_size 1024M;

# Increase timeouts
client_body_timeout 300s;
client_header_timeout 300s;
```

And in your PHP-FPM configuration:

```ini
; /etc/php/8.x/fpm/php.ini
upload_max_filesize = 1024M
post_max_size = 1024M
memory_limit = 1024M
max_execution_time = 300
max_input_time = 300
```

### Docker Configuration

If using Docker, add to your Dockerfile:

```dockerfile
# Custom PHP configuration
COPY php.ini /usr/local/etc/php/conf.d/uploads.ini
```

## Troubleshooting

### Common Upload Errors

1. **413 Payload Too Large**
   - Check web server `client_max_body_size` (Nginx) or `LimitRequestBody` (Apache)
   - Verify PHP `post_max_size` setting

2. **Upload timeout**
   - Increase `max_execution_time` and `max_input_time`
   - Check web server timeout settings

3. **Memory errors**
   - Increase PHP `memory_limit`
   - Consider using streaming uploads for very large files

### Checking Current Limits

Run this command to check current PHP limits:

```bash
php -r "echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL; echo 'post_max_size: ' . ini_get('post_max_size') . PHP_EOL; echo 'memory_limit: ' . ini_get('memory_limit') . PHP_EOL;"
```

### Testing Upload Limits

The application provides detailed error messages when uploads fail, including:

- Current PHP configuration limits
- File size information
- Specific error codes and messages

## Security Considerations

1. **File Type Validation**: Only allow specific video formats
2. **Virus Scanning**: Consider adding virus scanning for uploaded files
3. **Storage Limits**: Monitor disk space usage
4. **Rate Limiting**: Implement upload rate limiting per user
5. **Authentication**: Ensure only authenticated users can upload

## Performance Optimization

1. **Chunked Uploads**: Consider implementing chunked uploads for very large files
2. **Background Processing**: Video processing happens in background jobs
3. **Storage**: Consider using cloud storage (S3) for large files
4. **CDN**: Use a CDN for video delivery

## Monitoring

Monitor the following metrics:

- Upload success/failure rates
- Average upload times
- Storage usage
- Server resource usage during uploads
