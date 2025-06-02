<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class HLSKeyController extends Controller
{
    /**
     * Serve encryption key for HLS segments
     */
    public function getKey(Request $request, string $keyFileName): Response
    {
        // Validate key file name format
        if (!preg_match('/^key_[a-f0-9]{12}\.key$/', $keyFileName)) {
            Log::warning('Invalid key file name requested', [
                'key_file' => $keyFileName,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            abort(404);
        }

        // Find the key file in any video directory
        $keyFilePath = $this->findKeyFile($keyFileName);
        
        if (!$keyFilePath || !file_exists($keyFilePath)) {
            Log::warning('Key file not found', [
                'key_file' => $keyFileName,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            abort(404);
        }

        // Verify the request is coming from a valid source
        if (!$this->isValidKeyRequest($request)) {
            Log::warning('Unauthorized key request', [
                'key_file' => $keyFileName,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('referer')
            ]);
            abort(403);
        }

        // Log key access for security monitoring
        Log::info('HLS key accessed', [
            'key_file' => $keyFileName,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer')
        ]);

        // Read and serve the key file
        $keyContent = file_get_contents($keyFilePath);

        return response($keyContent, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => strlen($keyContent),
            'Cache-Control' => 'private, max-age=3600',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
        ]);
    }

    /**
     * Find key file in video directories
     */
    protected function findKeyFile(string $keyFileName): ?string
    {
        $hlsBasePath = storage_path('app/public/videos/hls');
        
        if (!is_dir($hlsBasePath)) {
            return null;
        }

        // Search through all video directories
        $directories = glob($hlsBasePath . '/*', GLOB_ONLYDIR);
        
        foreach ($directories as $directory) {
            $keyFilePath = $directory . '/' . $keyFileName;
            if (file_exists($keyFilePath)) {
                return $keyFilePath;
            }
        }

        return null;
    }

    /**
     * Validate if the key request is legitimate
     */
    protected function isValidKeyRequest(Request $request): bool
    {
        // Check if request has proper headers
        $userAgent = $request->userAgent();
        $referer = $request->header('referer');

        // Allow requests from known video players
        $validUserAgents = [
            'Mozilla/', // Browsers
            'VLC/', // VLC player
            'ffmpeg/', // FFmpeg
            'hls.js', // HLS.js library
        ];

        foreach ($validUserAgents as $validAgent) {
            if (str_contains($userAgent, $validAgent)) {
                return true;
            }
        }

        // Allow requests with valid referer from our domain
        if ($referer) {
            $allowedDomains = [
                config('app.url'),
                'http://localhost:3000',
                'https://localhost:3000',
                'http://127.0.0.1:3000',
                'https://127.0.0.1:3000',
            ];

            foreach ($allowedDomains as $domain) {
                if (str_starts_with($referer, $domain)) {
                    return true;
                }
            }
        }

        // For development, allow localhost requests
        if (app()->environment('local') && in_array($request->ip(), ['127.0.0.1', '::1', 'localhost'])) {
            return true;
        }

        return false;
    }
}
