<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Log;
use App\Models\Video;
use App\Models\ApiUsage;

class HLSController extends Controller
{
    /**
     * Serve HLS files (playlists and segments) with data transfer tracking
     */
    public function serve(Request $request, string $path): BinaryFileResponse
    {
        $filePath = storage_path('app/public/videos/hls/' . $path);

        if (!file_exists($filePath)) {
            abort(404);
        }

        // Determine MIME type
        $mimeType = 'application/vnd.apple.mpegurl';
        if (pathinfo($filePath, PATHINFO_EXTENSION) === 'ts') {
            $mimeType = 'video/mp2t';
        }

        // Get file size for data transfer tracking
        $fileSize = filesize($filePath);

        // Track data transfer for video segments (.ts files)
        if (str_ends_with($path, '.ts')) {
            $this->trackDataTransfer($request, $path, $fileSize);
        }

        // Create response
        $response = response()->file($filePath, [
            'Content-Type' => $mimeType,
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
        ]);

        return $response;
    }

    /**
     * Track data transfer for video segments
     */
    protected function trackDataTransfer(Request $request, string $path, int $fileSize): void
    {
        try {
            // Extract video ID from path (e.g., "62/seg_abc123.ts" -> video ID 62)
            $pathParts = explode('/', $path);
            if (count($pathParts) < 2) {
                return; // Can't determine video ID
            }

            $videoId = $pathParts[0];
            $video = Video::find($videoId);
            
            if (!$video) {
                Log::warning('Video not found for HLS chunk tracking', [
                    'video_id' => $videoId,
                    'path' => $path
                ]);
                return;
            }

            // Get user from video owner (since HLS requests don't have API key auth)
            $user = $video->user;
            if (!$user || !$user->activeSubscription) {
                return; // No subscription to track against
            }

            // Track data transfer in subscription usage
            $fileSizeGB = $fileSize / (1024 * 1024 * 1024); // Convert bytes to GB
            $user->activeSubscription->incrementUsage('data_transfer_gb', $fileSizeGB);

            // Also log in API usage if there's an API key in the request
            $apiKey = $request->attributes->get('api_key');
            if ($apiKey) {
                ApiUsage::logUsage(
                    $apiKey->id,
                    'hls/' . $path,
                    'GET',
                    $request->ip(),
                    $request->userAgent(),
                    200,
                    0,
                    $fileSize
                );
            }

            Log::info('HLS chunk data transfer tracked', [
                'video_id' => $videoId,
                'user_id' => $user->id,
                'file_size' => $fileSize,
                'file_size_gb' => $fileSizeGB,
                'path' => $path
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to track HLS data transfer', [
                'error' => $e->getMessage(),
                'path' => $path,
                'file_size' => $fileSize
            ]);
        }
    }

    /**
     * Handle OPTIONS requests for CORS
     */
    public function options(): Response
    {
        return response('', 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
        ]);
    }
}
