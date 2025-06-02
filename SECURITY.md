# Video Streaming Security Features

## 🔒 Advanced Video Security System

This video streaming platform implements enterprise-level security measures to protect video content from unauthorized access and content theft.

### 🎯 **Multi-Layer Anti-Piracy Features**

#### 1. **Separate Audio/Video Streams**

- **Problem**: Traditional HLS combines audio and video, making it easier to steal complete content
- **Solution**: Audio and video are processed as separate streams that are combined during playback
- **Security Benefit**: Even if someone downloads all chunks, they need to properly mux audio and video streams

#### 2. **Random Chunk Names**

- **Problem**: Predictable chunk names like `360p_000.ts`, `audio_000.ts` make it easy for scrapers
- **Solution**: Each segment gets a cryptographically secure random name like `seg_31a8b3a09101.ts`

#### 3. **Encrypted Mapping System**

- **Mapping File**: `.chunk_map.json` stores the relationship between original and secure names
- **Encryption**: Mappings are base64 encoded and stored with restricted permissions (600)
- **Organization**: System maintains order internally while appearing random externally

#### 4. **Security Headers**

- `X-Content-Type-Options: nosniff` - Prevents MIME type sniffing
- `X-Frame-Options: DENY` - Prevents embedding in iframes
- `Referrer-Policy: strict-origin-when-cross-origin` - Controls referrer information
- `Cache-Control: private, max-age=3600` - Private caching for segments

#### 5. **Access Monitoring**

- All chunk access is logged with IP, User-Agent, and Referrer
- Suspicious patterns can be detected and blocked
- Real-time monitoring of content access

### 🛠 **Technical Implementation**

#### **Audio/Video Separation Process:**

```
1. Original video is split into separate audio and video streams
2. Audio streams: 128k AAC, 64k AAC (audio-only)
3. Video streams: 360p, 720p, 1080p (video-only, no audio)
4. Each stream gets separate secure chunk names
5. Master playlist references both audio and video groups
6. Player automatically combines streams during playback
```

#### **Secure Chunk Generation:**

```
1. FFmpeg creates temporary segments: temp_audio_128k_000.ts, temp_360p_000.ts
2. System generates secure names: seg_780530e25cf1.ts, seg_14f6994ddcf4.ts
3. Files are renamed to secure names
4. Playlists are updated with secure references
5. Mapping is encrypted and stored for both audio and video
6. Temporary files are cleaned up
```

#### **Security Workflow:**

```
Original: 360p_000.ts → Secure: seg_31a8b3a09101.ts
Original: 720p_000.ts → Secure: seg_de27a0b8fdaf.ts
Original: 1080p_000.ts → Secure: seg_099f9adbd024.ts
```

#### **Master Playlist Example (Audio/Video Separation):**

```m3u8
#EXTM3U
#EXT-X-VERSION:6
#EXT-X-INDEPENDENT-SEGMENTS

# Audio tracks
#EXT-X-MEDIA:TYPE=AUDIO,GROUP-ID="audio",NAME="audio_128k",DEFAULT=YES,AUTOSELECT=YES,URI="audio_128k.m3u8"
#EXT-X-MEDIA:TYPE=AUDIO,GROUP-ID="audio",NAME="audio_64k",DEFAULT=YES,AUTOSELECT=YES,URI="audio_64k.m3u8"

# Video tracks
#EXT-X-STREAM-INF:BANDWIDTH=928000,RESOLUTION=640x360,AUDIO="audio"
360p.m3u8

#EXT-X-STREAM-INF:BANDWIDTH=2628000,RESOLUTION=1280x720,AUDIO="audio"
720p.m3u8
```

#### **Individual Playlist Example:**

```m3u8
#EXTM3U
#EXT-X-VERSION:3
#EXT-X-TARGETDURATION:10
#EXT-X-MEDIA-SEQUENCE:0
#EXTINF:9.733333,
seg_14f6994ddcf4.ts
#EXT-X-ENDLIST
```

### 🔐 **Security Benefits**

#### **For Content Creators:**

- ✅ **Harder to scrape**: Random names make bulk downloading difficult
- ✅ **Access tracking**: Monitor who's accessing your content
- ✅ **Hotlink protection**: Prevent unauthorized embedding
- ✅ **Organized internally**: System maintains proper playback order

#### **For Platform Operators:**

- ✅ **Bandwidth protection**: Prevent unauthorized hotlinking
- ✅ **Usage analytics**: Detailed access logs
- ✅ **Scalable security**: Works with any number of videos
- ✅ **Performance**: Minimal overhead on streaming

### 📊 **Security Levels**

#### **Level 1: Basic Protection**

- Random chunk names
- Security headers
- Access logging

#### **Level 2: Enhanced Protection** (Future)

- Token-based authentication
- Time-limited URLs
- IP-based restrictions

#### **Level 3: Advanced Protection** (Future)

- DRM integration
- Watermarking
- Real-time threat detection

### 🚀 **Usage**

#### **Automatic Processing:**

All videos uploaded through the platform automatically get secure chunk names. No additional configuration required.

#### **Manual Reprocessing:**

```bash
php artisan video:reprocess {video_id}
```

#### **Monitoring Logs:**

```bash
tail -f storage/logs/laravel.log | grep "Secure chunk accessed"
```

### 🔍 **Example Security Log:**

```json
{
  "message": "Secure chunk accessed",
  "context": {
    "path": "api/hls/7/seg_31a8b3a09101.ts",
    "ip": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "referer": "http://localhost:3000"
  }
}
```

### ⚠️ **Important Notes**

1. **Mapping Files**: Never expose `.chunk_map.json` files publicly
2. **Permissions**: Mapping files have 600 permissions (owner read/write only)
3. **Backup**: Include mapping files in your backup strategy
4. **Monitoring**: Regularly review access logs for suspicious activity

### 🎬 **Impact on Streaming**

- ✅ **No performance impact**: Streaming works exactly the same
- ✅ **Full compatibility**: Works with all HLS players
- ✅ **Adaptive streaming**: All quality levels supported
- ✅ **Transparent to users**: End users see no difference

This security system significantly raises the barrier for content theft while maintaining full compatibility with standard HLS streaming protocols.
