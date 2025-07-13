<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Services\StripeService;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Stripe Integration Test ===" . PHP_EOL . PHP_EOL;

try {
    // Get test user and plan
    $user = User::first();
    $plan = SubscriptionPlan::where('slug', 'professional')->first();

    echo "✅ User: {$user->name} ({$user->email})" . PHP_EOL;
    echo "✅ Plan: {$plan->name} - \${$plan->price}/month" . PHP_EOL . PHP_EOL;

    // Initialize Stripe service
    $stripeService = new StripeService();

    echo "🔧 Testing Stripe Service..." . PHP_EOL;

    // Test getting publishable key
    $publishableKey = $stripeService->getPublishableKey();
    echo "✅ Publishable Key: " . substr($publishableKey, 0, 20) . "..." . PHP_EOL;

    // Test creating checkout session
    echo "🛒 Creating checkout session..." . PHP_EOL;
    $checkoutData = $stripeService->createCheckoutSession($user, $plan);

    echo "✅ Checkout URL created: " . substr($checkoutData['checkout_url'], 0, 50) . "..." . PHP_EOL;
    echo "✅ Session ID: " . $checkoutData['session_id'] . PHP_EOL . PHP_EOL;

    echo "🎉 Stripe integration test completed successfully!" . PHP_EOL;
    echo "📋 Next steps:" . PHP_EOL;
    echo "   1. Visit the checkout URL to complete payment" . PHP_EOL;
    echo "   2. Use the session ID to handle checkout success" . PHP_EOL;
    echo "   3. Test the frontend integration" . PHP_EOL;

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
}

echo PHP_EOL . "=== Test Complete ===" . PHP_EOL;
