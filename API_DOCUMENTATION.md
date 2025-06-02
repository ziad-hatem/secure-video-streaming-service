# Video Streaming API Documentation

## Overview

The Video Streaming API provides secure access to video upload, processing, and streaming capabilities with API key authentication and usage monitoring.

## Authentication

All API requests require authentication using an API key. Include your API key in the request header:

```
X-API-Key: your_api_key_here
```

Or as a Bearer token:

```
Authorization: Bearer your_api_key_here
```

## Base URL

```
https://your-domain.com/api/v1
```

## API Endpoints

### User Information

#### Get User Info
```
GET /user
```

Returns information about the authenticated user and their subscription.

**Response:**
```json
{
  "user": {
    "id": 1,
    "name": "Test User",
    "email": "test@example.com",
    "account_type": "business",
    "company_name": "Test Company"
  },
  "api_key": {
    "id": 1,
    "name": "Production API Key",
    "permissions": ["*"],
    "last_used_at": "2025-06-02T10:43:36.000000Z"
  },
  "subscription": {
    "plan": "Professional",
    "status": "active",
    "ends_at": "2025-07-02T10:43:36.000000Z"
  }
}
```

### Videos

#### List Videos
```
GET /videos
```

Returns a paginated list of videos for the authenticated user.

**Query Parameters:**
- `page` (optional): Page number for pagination

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Sample Video",
      "description": "A sample video",
      "status": "completed",
      "duration": 120,
      "file_size": 1048576,
      "created_at": "2025-06-02T10:43:36.000000Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

#### Get Video Details
```
GET /videos/{id}
```

Returns detailed information about a specific video.

#### Upload Video
```
POST /videos/upload
```

Upload a new video for processing.

**Request Body (multipart/form-data):**
- `title` (required): Video title
- `description` (optional): Video description
- `video` (required): Video file (max 1GB)

**Response:**
```json
{
  "message": "Video uploaded successfully",
  "video": {
    "id": 1,
    "title": "Sample Video",
    "status": "uploading",
    "created_at": "2025-06-02T10:43:36.000000Z"
  }
}
```

#### Get Streaming URL
```
GET /videos/{id}/stream
```

Returns the HLS streaming URL and metadata for a completed video.

**Response:**
```json
{
  "hls_url": "https://your-domain.com/api/hls/video_123/playlist.m3u8",
  "thumbnail_url": "https://your-domain.com/storage/thumbnails/video_123.jpg",
  "resolutions": ["360p", "720p", "1080p"],
  "duration": 120
}
```

## API Key Management (Web Dashboard)

These endpoints require Sanctum authentication and are intended for web dashboard use:

### List API Keys
```
GET /api-keys
```

### Create API Key
```
POST /api-keys
```

### Get API Key Details
```
GET /api-keys/{id}
```

### Update API Key
```
PUT /api-keys/{id}
```

### Regenerate API Key
```
POST /api-keys/{id}/regenerate
```

### Delete API Key
```
DELETE /api-keys/{id}
```

### Get Usage Statistics
```
GET /api-keys/usage?period=month
```

## Subscription Management (Web Dashboard)

### Get Available Plans
```
GET /subscription/plans
```

### Get Current Subscription
```
GET /subscription/current
```

### Subscribe to Plan
```
POST /subscription/subscribe
```

### Change Plan
```
PUT /subscription/change-plan
```

### Cancel Subscription
```
POST /subscription/cancel
```

### Get Usage Statistics
```
GET /subscription/usage
```

## Rate Limits

Rate limits are enforced based on your subscription plan:

- **Starter**: 10,000 API calls/month
- **Professional**: 100,000 API calls/month  
- **Enterprise**: 1,000,000 API calls/month

## Error Responses

The API returns standard HTTP status codes:

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized (invalid API key)
- `402` - Payment Required (no active subscription)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found
- `422` - Validation Error
- `429` - Too Many Requests (rate limit exceeded)
- `500` - Internal Server Error

Error response format:
```json
{
  "error": "Error type",
  "message": "Detailed error message"
}
```

## Getting Started

1. Sign up for an account
2. Choose a subscription plan
3. Generate an API key from the dashboard
4. Start making API requests with your key

## Support

For API support, contact us at support@your-domain.com
