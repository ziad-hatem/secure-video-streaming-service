<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'secure.chunk' => \App\Http\Middleware\SecureChunkMiddleware::class,
            'api.key' => \App\Http\Middleware\ApiKeyMiddleware::class,
        ]);

        // Don't apply Sanctum stateful middleware to all API routes
        // We'll handle authentication per route group instead
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
