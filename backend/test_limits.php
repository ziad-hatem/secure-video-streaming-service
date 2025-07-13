<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\UserSubscription;
use App\Models\SubscriptionPlan;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Video Streaming Limits Test ===" . PHP_EOL . PHP_EOL;

// Get test user and subscription
$user = User::first();
$subscription = $user->activeSubscription;

if (!$subscription) {
    echo "❌ No active subscription found" . PHP_EOL;
    exit(1);
}

echo "✅ User: {$user->name} ({$user->email})" . PHP_EOL;
echo "✅ Plan: {$subscription->plan->name}" . PHP_EOL;
echo "✅ Subscription Status: {$subscription->status}" . PHP_EOL . PHP_EOL;

// Display current limits
echo "📊 Plan Limits:" . PHP_EOL;
echo "   - API Calls: " . number_format($subscription->plan->getLimit('api_calls_per_month')) . "/month" . PHP_EOL;
echo "   - Video Streams: " . number_format($subscription->plan->getLimit('video_streams_per_month')) . "/month" . PHP_EOL;
echo "   - Data Transfer: " . $subscription->plan->getLimit('data_transfer_gb') . " GB/month" . PHP_EOL;
echo "   - Video Uploads: " . $subscription->plan->getLimit('max_video_uploads_per_month') . "/month" . PHP_EOL . PHP_EOL;

// Display current usage
echo "📈 Current Usage:" . PHP_EOL;
echo "   - API Calls: " . number_format($subscription->getCurrentUsage('api_calls_per_month')) . PHP_EOL;
echo "   - Video Streams: " . number_format($subscription->getCurrentUsage('video_streams_per_month')) . PHP_EOL;
echo "   - Data Transfer: " . round($subscription->getCurrentUsage('data_transfer_gb'), 3) . " GB" . PHP_EOL;
echo "   - Video Uploads: " . number_format($subscription->getCurrentUsage('max_video_uploads_per_month')) . PHP_EOL . PHP_EOL;

// Check if limits are exceeded
echo "🚦 Limit Status:" . PHP_EOL;
echo "   - Video Streams: " . ($user->hasExceededUsageLimit('video_streams_per_month') ? "❌ EXCEEDED" : "✅ OK") . PHP_EOL;
echo "   - Data Transfer: " . ($user->hasExceededUsageLimit('data_transfer_gb') ? "❌ EXCEEDED" : "✅ OK") . PHP_EOL;
echo "   - API Calls: " . ($user->hasExceededUsageLimit('api_calls_per_month') ? "❌ EXCEEDED" : "✅ OK") . PHP_EOL . PHP_EOL;

// Test API endpoint
echo "🧪 Testing API Endpoint:" . PHP_EOL;
$apiKey = $user->apiKeys()->first();
if ($apiKey) {
    echo "   - API Key: {$apiKey->getMaskedKeyAttribute()}" . PHP_EOL;
    
    // Test video streaming endpoint
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/v1/videos');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $apiKey->key,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "   - Video List Endpoint: ";
    if ($httpCode === 200) {
        echo "✅ SUCCESS (HTTP $httpCode)" . PHP_EOL;
    } else {
        echo "❌ FAILED (HTTP $httpCode)" . PHP_EOL;
        $responseData = json_decode($response, true);
        if (isset($responseData['error'])) {
            echo "     Error: {$responseData['error']}" . PHP_EOL;
            echo "     Message: {$responseData['message']}" . PHP_EOL;
        }
    }
} else {
    echo "   - ❌ No API key found" . PHP_EOL;
}

echo PHP_EOL . "=== Test Complete ===" . PHP_EOL;
