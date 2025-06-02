<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Video extends Model
{
    protected $fillable = [
        'title',
        'description',
        'original_filename',
        'original_path',
        'hls_path',
        'resolutions',
        'status',
        'duration',
        'file_size',
        'thumbnail_path',
        'user_id',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'resolutions' => 'array',
        'file_size' => 'integer',
        'duration' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getHlsUrlAttribute(): string
    {
        if (!$this->hls_path) {
            return '';
        }

        // Extract the path after 'videos/hls/'
        $hlsPath = str_replace('videos/hls/', '', $this->hls_path);
        return url('api/hls/' . $hlsPath);
    }

    public function getThumbnailUrlAttribute(): string
    {
        return $this->thumbnail_path ? url('storage/' . $this->thumbnail_path) : '';
    }
}
