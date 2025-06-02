<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'starts_at',
        'ends_at',
        'status',
        'usage_stats',
        'stripe_subscription_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'usage_stats' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * Check if subscription is currently active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' &&
               $this->starts_at->isPast() &&
               ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /**
     * Get current usage for a specific metric
     */
    public function getCurrentUsage(string $metric): int
    {
        return $this->usage_stats[$metric] ?? 0;
    }

    /**
     * Increment usage for a specific metric
     */
    public function incrementUsage(string $metric, int $amount = 1): void
    {
        $stats = $this->usage_stats ?? [];
        $stats[$metric] = ($stats[$metric] ?? 0) + $amount;
        $this->update(['usage_stats' => $stats]);
    }

    /**
     * Check if usage limit is exceeded for a metric
     */
    public function isUsageLimitExceeded(string $metric): bool
    {
        $limit = $this->plan->getLimit($metric);
        if ($limit === null) {
            return false; // No limit set
        }

        return $this->getCurrentUsage($metric) >= $limit;
    }

    /**
     * Reset usage stats (typically called monthly)
     */
    public function resetUsageStats(): void
    {
        $this->update(['usage_stats' => []]);
    }

    /**
     * Scope for active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('starts_at', '<=', now())
                    ->where(function ($q) {
                        $q->whereNull('ends_at')
                          ->orWhere('ends_at', '>', now());
                    });
    }
}
