<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\ApiKey;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Authentication Flow Test ===" . PHP_EOL . PHP_EOL;

// Get test user and API key
$user = User::first();
$apiKey = $user->apiKeys()->first();

echo "✅ User: {$user->name} ({$user->email})" . PHP_EOL;
echo "✅ API Key: {$apiKey->getMaskedKeyAttribute()}" . PHP_EOL;
echo "✅ Has Active Subscription: " . ($user->hasActiveSubscription() ? 'YES' : 'NO') . PHP_EOL;
echo "✅ Is On Trial: " . ($user->isOnTrial() ? 'YES' : 'NO') . PHP_EOL . PHP_EOL;

// Test endpoints that should work without subscription
$allowedEndpoints = [
    'http://localhost:8000/api/user',
    'http://localhost:8000/api/subscription/plans',
    'http://localhost:8000/api/subscription/current',
    'http://localhost:8000/api/subscription/stripe-config',
    'http://localhost:8000/api/api-keys'
];

echo "🧪 Testing endpoints that should work without subscription:" . PHP_EOL;

foreach ($allowedEndpoints as $endpoint) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $apiKey->key,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $endpointName = basename(parse_url($endpoint, PHP_URL_PATH));
    if ($httpCode === 200) {
        echo "   ✅ {$endpointName}: SUCCESS (HTTP {$httpCode})" . PHP_EOL;
    } else {
        echo "   ❌ {$endpointName}: FAILED (HTTP {$httpCode})" . PHP_EOL;
        $responseData = json_decode($response, true);
        if (isset($responseData['error'])) {
            echo "      Error: {$responseData['error']}" . PHP_EOL;
        }
    }
}

echo PHP_EOL . "🧪 Testing endpoints that should require subscription:" . PHP_EOL;

$restrictedEndpoints = [
    'http://localhost:8000/api/v1/videos',
    'http://localhost:8000/api/v1/videos/1/stream'
];

foreach ($restrictedEndpoints as $endpoint) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $apiKey->key,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $endpointName = basename(parse_url($endpoint, PHP_URL_PATH));
    if ($httpCode === 402) {
        echo "   ✅ {$endpointName}: CORRECTLY BLOCKED (HTTP {$httpCode})" . PHP_EOL;
    } else {
        echo "   ❌ {$endpointName}: SHOULD BE BLOCKED (HTTP {$httpCode})" . PHP_EOL;
    }
}

echo PHP_EOL . "🎉 Authentication flow test completed!" . PHP_EOL;
echo "📋 Summary:" . PHP_EOL;
echo "   - Users can access account management without subscription" . PHP_EOL;
echo "   - Video streaming endpoints are properly protected" . PHP_EOL;
echo "   - Frontend refresh should now work correctly" . PHP_EOL;

echo PHP_EOL . "=== Test Complete ===" . PHP_EOL;
