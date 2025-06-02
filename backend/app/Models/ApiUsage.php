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
        $query = self::where('api_key_id', $apiKeyId);

        switch ($period) {
            case 'day':
                $query->where('created_at', '>=', now()->startOfDay());
                break;
            case 'week':
                $query->where('created_at', '>=', now()->startOfWeek());
                break;
            case 'month':
                $query->where('created_at', '>=', now()->startOfMonth());
                break;
            case 'year':
                $query->where('created_at', '>=', now()->startOfYear());
                break;
        }

        return [
            'total_requests' => $query->count(),
            'successful_requests' => $query->where('response_status', '<', 400)->count(),
            'failed_requests' => $query->where('response_status', '>=', 400)->count(),
            'total_bytes' => $query->sum('bytes_transferred') ?? 0,
            'avg_response_time' => $query->avg('response_time_ms') ?? 0,
        ];
    }
}
