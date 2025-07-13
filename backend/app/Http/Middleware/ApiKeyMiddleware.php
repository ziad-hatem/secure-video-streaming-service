<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiUsage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission = null): Response
    {
        $startTime = microtime(true);

        // Get API key from header
        $apiKeyValue = $request->header('X-API-Key') ?? $request->header('Authorization');

        if (!$apiKeyValue) {
            return response()->json([
                'error' => 'API key required',
                'message' => 'Please provide an API key in the X-API-Key header'
            ], 401);
        }

        // Remove "Bearer " prefix if present
        $apiKeyValue = str_replace('Bearer ', '', $apiKeyValue);

        // Find the API key
        $apiKey = ApiKey::where('key', $apiKeyValue)
                        ->where('is_active', true)
                        ->with(['user.activeSubscription.plan'])
                        ->first();

        if (!$apiKey || !$apiKey->isValid()) {
            return response()->json([
                'error' => 'Invalid API key',
                'message' => 'The provided API key is invalid or expired'
            ], 401);
        }

        // Check if user account is active
        if (!$apiKey->user->is_active) {
            return response()->json([
                'error' => 'Account suspended',
                'message' => 'Your account has been suspended'
            ], 403);
        }

        // Check permission if specified
        if ($permission && !$apiKey->hasPermission($permission)) {
            return response()->json([
                'error' => 'Insufficient permissions',
                'message' => "This API key does not have the '{$permission}' permission"
            ], 403);
        }

        // Check subscription and usage limits
        $user = $apiKey->user->fresh(); // Get fresh user data from database
        $user->unsetRelation('activeSubscription'); // Clear cached relationship

        // Define endpoints that don't require active subscription
        $allowedWithoutSubscription = [
            'user',
            'subscription/plans',
            'subscription/current',
            'subscription/subscribe',
            'subscription/checkout-success',
            'subscription/stripe-config',
            'api-keys',
            'auth/logout'
        ];

        $currentPath = $request->path();
        $requiresSubscription = true;

        foreach ($allowedWithoutSubscription as $allowedPath) {
            if (str_contains($currentPath, $allowedPath)) {
                $requiresSubscription = false;
                break;
            }
        }

        if ($requiresSubscription && !$user->isOnTrial() && !$user->hasActiveSubscription()) {
            // Debug: Check what's happening
            $activeSub = $user->activeSubscription;
            \Log::info('Subscription check failed', [
                'user_id' => $user->id,
                'is_on_trial' => $user->isOnTrial(),
                'has_active_subscription' => $user->hasActiveSubscription(),
                'active_subscription_id' => $activeSub ? $activeSub->id : null,
                'path' => $request->path(),
            ]);

            return response()->json([
                'error' => 'No active subscription',
                'message' => 'Please subscribe to a plan to continue using the API'
            ], 402); // Payment Required
        }

        // Check API call limits (only for users with active subscriptions)
        if ($user->hasActiveSubscription() && $user->hasExceededUsageLimit('api_calls_per_month')) {
            return response()->json([
                'error' => 'Usage limit exceeded',
                'message' => 'You have exceeded your monthly API call limit'
            ], 429); // Too Many Requests
        }

        // Check data transfer limits (only for users with active subscriptions)
        if ($user->hasActiveSubscription() && $user->hasExceededUsageLimit('data_transfer_gb')) {
            return response()->json([
                'error' => 'Data transfer limit exceeded',
                'message' => 'You have exceeded your monthly data transfer limit'
            ], 429); // Too Many Requests
        }

        // Set the authenticated user and API key in the request
        $request->setUserResolver(function () use ($apiKey) {
            return $apiKey->user;
        });
        $request->attributes->set('api_key', $apiKey);

        // Process the request
        $response = $next($request);

        // Log the API usage
        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000); // Convert to milliseconds

        ApiUsage::logUsage(
            $apiKey->id,
            $request->path(),
            $request->method(),
            $request->ip(),
            $request->userAgent(),
            $response->getStatusCode(),
            $responseTime,
            strlen($response->getContent())
        );

        // Update API key last used timestamp
        $apiKey->markAsUsed();

        // Increment usage counters
        if ($user->activeSubscription) {
            $user->activeSubscription->incrementUsage('api_calls_per_month');

            // Track data transfer for API responses (but not for HLS chunks - those are tracked separately)
            if (!$request->is('hls/*')) {
                $responseSize = strlen($response->getContent());
                if ($responseSize > 0) {
                    $responseSizeGB = $responseSize / (1024 * 1024 * 1024); // Convert bytes to GB
                    $user->activeSubscription->incrementUsage('data_transfer_gb', $responseSizeGB);
                }
            }
        }

        return $response;
    }
}
