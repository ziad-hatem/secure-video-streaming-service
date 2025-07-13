<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiUsage extends Model
{
    protected $table = 'api_usage';
    public $timestamps = true;

    protected $fillable = [
        'api_key_id',
        'endpoint',
        'method',
        'ip_address',
        'user_agent',
        'response_status',
        'response_time_ms',
        'bytes_transferred',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * Log API usage
     */
    public static function logUsage(
        int $apiKeyId,
        string $endpoint,
        string $method,
        string $ipAddress,
        ?string $userAgent = null,
        int $responseStatus = 200,
        ?int $responseTimeMs = null,
        ?int $bytesTransferred = null
    ): void {
        self::create([
            'api_key_id' => $apiKeyId,
            'endpoint' => $endpoint,
            'method' => $method,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'response_status' => $responseStatus,
            'response_time_ms' => $responseTimeMs,
            'bytes_transferred' => $bytesTransferred,
        ]);
    }

    /**
     * Get usage statistics for a specific API key
     */
    public static function getUsageStats(int $apiKeyId, string $period = 'month'): array
    {
        // Create base query with API key filter
        $baseQuery = self::where('api_key_id', $apiKeyId);

        // Apply time period filter
        switch ($period) {
            case 'day':
                $baseQuery->where('created_at', '>=', now()->startOfDay());
                break;
            case 'week':
                $baseQuery->where('created_at', '>=', now()->startOfWeek());
                break;
            case 'month':
                $baseQuery->where('created_at', '>=', now()->startOfMonth());
                break;
            case 'year':
                $baseQuery->where('created_at', '>=', now()->startOfYear());
                break;
        }

        // Clone the base query for each statistic to avoid query pollution
        return [
            'total_requests' => (int) ((clone $baseQuery)->count()),
            'successful_requests' => (int) ((clone $baseQuery)->where('response_status', '<', 400)->count()),
            'failed_requests' => (int) ((clone $baseQuery)->where('response_status', '>=', 400)->count()),
            'total_bytes' => (int) ((clone $baseQuery)->sum('bytes_transferred') ?? 0),
            'avg_response_time' => (float) ((clone $baseQuery)->avg('response_time_ms') ?? 0),
        ];
    }
}
