<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // API routes don't need CSRF protection
        'api/*',
        
        // Subscription routes specifically
        'subscription/*',
        
        // Stripe webhook endpoints (if you add them later)
        'stripe/*',
        'webhooks/*',
        
        // HLS streaming endpoints
        'hls/*',
    ];
}
