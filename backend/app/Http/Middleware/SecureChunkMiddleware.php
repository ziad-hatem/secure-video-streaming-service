<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class SecureChunkMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Add security headers for chunk requests
        $response = $next($request);

        // Add anti-hotlinking headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Add cache control for segments
        if ($request->is('api/hls/*') && str_ends_with($request->path(), '.ts')) {
            $response->headers->set('Cache-Control', 'private, max-age=3600');

            // Log chunk access for monitoring
            Log::info('Secure chunk accessed', [
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('referer')
            ]);
        }

        return $response;
    }
}
