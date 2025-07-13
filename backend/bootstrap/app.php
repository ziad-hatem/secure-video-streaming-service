<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Debug routes (only in development)
            if (app()->environment('local')) {
                Route::prefix('debug')->group(base_path('routes/debug.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'secure.chunk' => \App\Http\Middleware\SecureChunkMiddleware::class,
            'api.key' => \App\Http\Middleware\ApiKeyMiddleware::class,
            'disable.csrf' => \App\Http\Middleware\DisableCsrfForApi::class,
        ]);

        // Enable Sanctum stateful middleware for web dashboard authentication
        $middleware->statefulApi();

        // Use custom CSRF middleware that excludes API routes
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'subscription/*',
            'stripe/*',
            'webhooks/*',
            'hls/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
