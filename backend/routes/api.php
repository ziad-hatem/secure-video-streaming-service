<?php

use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\VideoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes (no authentication required)
Route::prefix('auth')->middleware('disable.csrf')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::get('/subscription/plans', [SubscriptionController::class, 'plans'])->middleware('disable.csrf');

// Routes for web dashboard (using Sanctum Bearer token authentication)
Route::middleware(['auth:sanctum', 'disable.csrf'])->group(function () {
    // Auth routes for web dashboard
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'changePassword']);

    // API Key Management (for web dashboard)
    Route::prefix('api-keys')->group(function () {
        Route::get('/', [ApiKeyController::class, 'index']);
        Route::post('/', [ApiKeyController::class, 'store']);
        Route::get('/usage', [ApiKeyController::class, 'usage']);
        Route::get('/{apiKey}', [ApiKeyController::class, 'show']);
        Route::put('/{apiKey}', [ApiKeyController::class, 'update']);
        Route::post('/{apiKey}/regenerate', [ApiKeyController::class, 'regenerate']);
        Route::delete('/{apiKey}', [ApiKeyController::class, 'destroy']);
    });

    // Subscription Management (for web dashboard)
    Route::prefix('subscription')->group(function () {
        Route::get('/current', [SubscriptionController::class, 'current']);
        Route::post('/subscribe', [SubscriptionController::class, 'subscribe']);
        Route::put('/change-plan', [SubscriptionController::class, 'changePlan']);
        Route::post('/cancel', [SubscriptionController::class, 'cancel']);
        Route::get('/usage', [SubscriptionController::class, 'usage']);
        Route::post('/checkout-success', [SubscriptionController::class, 'handleCheckoutSuccess']);
        Route::get('/stripe-config', [SubscriptionController::class, 'getStripeConfig']);
    });

    // Video Management (for web dashboard) - using Sanctum auth
    Route::prefix('dashboard/videos')->group(function () {
        Route::get('/', [VideoController::class, 'dashboardIndex']);
        Route::post('/upload', [VideoController::class, 'dashboardUpload']);
        Route::get('/{video}', [VideoController::class, 'dashboardShow']);
        Route::delete('/{video}', [VideoController::class, 'dashboardDestroy']);
    });
});

// API routes that require API key authentication
Route::middleware(['api.key', 'disable.csrf'])->prefix('v1')->group(function () {
    // Video routes with API key authentication
    Route::prefix('videos')->group(function () {
        Route::get('/', [VideoController::class, 'index'])->middleware('api.key:videos.read');
        Route::post('/upload', [VideoController::class, 'upload'])->middleware('api.key:videos.write');
        Route::get('/{video}', [VideoController::class, 'show'])->middleware('api.key:videos.read');
        Route::get('/{video}/stream', [VideoController::class, 'stream'])->middleware('api.key:videos.read');
        Route::delete('/{video}', [VideoController::class, 'destroy'])->middleware('api.key:videos.write');
    });

    // User info endpoint for API users
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        $apiKey = $request->attributes->get('api_key');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'account_type' => $user->account_type,
                'company_name' => $user->company_name,
            ],
            'api_key' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'permissions' => $apiKey->permissions,
                'last_used_at' => $apiKey->last_used_at,
            ],
            'subscription' => $user->activeSubscription ? [
                'plan' => $user->activeSubscription->plan->name,
                'status' => $user->activeSubscription->status,
                'ends_at' => $user->activeSubscription->ends_at,
            ] : null,
        ]);
    });
});

// HLS encryption key serving routes (must come BEFORE general hls/{path} route)
Route::get('hls/key/{keyFileName}', [App\Http\Controllers\HLSKeyController::class, 'getKey'])
    ->where('keyFileName', '.*\.key')
    ->middleware('disable.csrf');

// HLS streaming routes with CORS headers and security
Route::get('hls/{path}', [App\Http\Controllers\HLSController::class, 'serve'])
    ->where('path', '.*')
    ->middleware(['secure.chunk', 'disable.csrf']);

// OPTIONS route for HLS files
Route::options('hls/{path}', [App\Http\Controllers\HLSController::class, 'options'])
    ->where('path', '.*')
    ->middleware('disable.csrf');

// CORS preflight
Route::options('{any}', function () {
    return response('', 200);
})->where('any', '.*')->middleware('disable.csrf');
