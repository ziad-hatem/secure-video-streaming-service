<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'key',
        'permissions',
        'last_used_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'permissions' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'key', // Hide the actual key in JSON responses for security
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function usage(): HasMany
    {
        return $this->hasMany(ApiUsage::class);
    }

    /**
     * Generate a new API key
     */
    public static function generateKey(): string
    {
        return 'vsk_' . Str::random(40); // vsk = Video Streaming Key
    }

    /**
     * Check if the API key has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        if (empty($this->permissions)) {
            return false;
        }

        return in_array($permission, $this->permissions) || in_array('*', $this->permissions);
    }

    /**
     * Check if the API key is valid and active
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Update the last used timestamp
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Get masked key for display
     */
    public function getMaskedKeyAttribute(): string
    {
        return substr($this->key, 0, 8) . '...' . substr($this->key, -4);
    }
}
