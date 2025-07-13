<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DisableCsrfForApi
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Disable CSRF protection for API routes
        if ($request->is('api/*') ||
            $request->is('subscription/*') ||
            $request->is('stripe/*') ||
            $request->is('webhooks/*') ||
            $request->is('hls/*')) {

            // Remove CSRF token requirement (only if session exists)
            if ($request->hasSession()) {
                $request->session()->regenerateToken();
            }
        }

        return $next($request);
    }
}
