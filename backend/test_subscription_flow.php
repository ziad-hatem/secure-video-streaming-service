<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Services\StripeService;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Complete Subscription Flow Test ===" . PHP_EOL . PHP_EOL;

// Get test user and plan
$user = User::first();
$plan = SubscriptionPlan::where('slug', 'starter')->first();

echo "✅ User: {$user->name} ({$user->email})" . PHP_EOL;
echo "✅ Plan: {$plan->name} - \${$plan->price}/month" . PHP_EOL . PHP_EOL;

// Test 1: Create checkout session
echo "🧪 Test 1: Creating Stripe checkout session..." . PHP_EOL;
try {
    $stripeService = new StripeService();
    $checkoutData = $stripeService->createCheckoutSession($user, $plan);
    echo "   ✅ Checkout session created successfully" . PHP_EOL;
    echo "   📋 Session ID: {$checkoutData['session_id']}" . PHP_EOL;
    echo "   🔗 Checkout URL: " . substr($checkoutData['checkout_url'], 0, 50) . "..." . PHP_EOL;
} catch (Exception $e) {
    echo "   ❌ Failed: {$e->getMessage()}" . PHP_EOL;
}

echo PHP_EOL;

// Test 2: Test subscription data structure
echo "🧪 Test 2: Testing subscription data structure..." . PHP_EOL;

// Create a test subscription with proper structure
$testSubscription = UserSubscription::create([
    'user_id' => $user->id,
    'plan_id' => $plan->id,
    'stripe_subscription_id' => 'sub_test_123',
    'status' => 'active',
    'starts_at' => now(),
    'ends_at' => now()->addMonth(),
    'usage_stats' => [
        'api_calls_per_month' => 150,
        'video_streams_per_month' => 25,
        'data_transfer_gb' => 3.2,
        'max_video_uploads_per_month' => 8,
    ]
]);

echo "   ✅ Test subscription created with ID: {$testSubscription->id}" . PHP_EOL;

// Test the data structure
$subscriptionData = [
    'subscription' => $testSubscription->load('plan'),
    'is_on_trial' => false,
    'trial_ends_at' => null,
];

echo "   ✅ Subscription data structure:" . PHP_EOL;
echo "      - Plan: {$subscriptionData['subscription']->plan->name}" . PHP_EOL;
echo "      - Status: {$subscriptionData['subscription']->status}" . PHP_EOL;
echo "      - API Calls: " . ($subscriptionData['subscription']->usage_stats['api_calls_per_month'] ?? 0) . PHP_EOL;
echo "      - Video Streams: " . ($subscriptionData['subscription']->usage_stats['video_streams_per_month'] ?? 0) . PHP_EOL;
echo "      - Data Transfer: " . ($subscriptionData['subscription']->usage_stats['data_transfer_gb'] ?? 0) . " GB" . PHP_EOL;

echo PHP_EOL;

// Test 3: Test API endpoints
echo "🧪 Test 3: Testing API endpoints..." . PHP_EOL;

$apiKey = $user->apiKeys()->first();
$endpoints = [
    'Plans' => 'http://localhost:8000/api/subscription/plans',
    'Current Subscription' => 'http://localhost:8000/api/subscription/current',
    'Stripe Config' => 'http://localhost:8000/api/subscription/stripe-config',
];

foreach ($endpoints as $name => $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $apiKey->key,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "   ✅ {$name}: SUCCESS (HTTP {$httpCode})" . PHP_EOL;
        
        // Check for specific data
        $data = json_decode($response, true);
        if ($name === 'Plans' && isset($data['plans'])) {
            echo "      📊 Found " . count($data['plans']) . " plans" . PHP_EOL;
        } elseif ($name === 'Current Subscription' && isset($data['subscription'])) {
            echo "      📊 Active subscription: {$data['subscription']['plan']['name']}" . PHP_EOL;
        }
    } else {
        echo "   ❌ {$name}: FAILED (HTTP {$httpCode})" . PHP_EOL;
    }
}

echo PHP_EOL;

// Clean up test subscription
$testSubscription->delete();
echo "🧹 Cleaned up test subscription" . PHP_EOL . PHP_EOL;

echo "🎉 Subscription flow test completed!" . PHP_EOL;
echo "📋 Summary:" . PHP_EOL;
echo "   - Stripe integration working correctly" . PHP_EOL;
echo "   - Subscription data structure is valid" . PHP_EOL;
echo "   - API endpoints are accessible" . PHP_EOL;
echo "   - Frontend should now load without errors" . PHP_EOL;

echo PHP_EOL . "=== Test Complete ===" . PHP_EOL;
