<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\VideoProcessingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class VideoController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        // Get the authenticated user (either from Sanctum or API key)
        $user = $request->user();

        // If user is authenticated, show only their videos
        if ($user) {
            $videos = Video::where('user_id', $user->id)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        } else {
            // For public access, show all videos (you might want to restrict this)
            $videos = Video::with('user')
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        }

        return response()->json($videos);
    }

    public function show(Video $video): JsonResponse
    {
        $video->load('user');
        return response()->json($video);
    }

    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video' => 'required|file|mimes:mp4,avi,mov,wmv,flv|max:1048576', // 1GB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('video');
            $originalName = $file->getClientOriginalName();
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

            // Store original video
            $originalPath = $file->storeAs('videos/originals', $filename, 'public');

            // Get the authenticated user
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'error' => 'Authentication required',
                    'message' => 'You must be authenticated to upload videos'
                ], 401);
            }

            // Check upload limits
            if ($user->hasExceededUsageLimit('max_video_uploads_per_month')) {
                return response()->json([
                    'error' => 'Upload limit exceeded',
                    'message' => 'You have exceeded your monthly video upload limit'
                ], 429);
            }

            // Create video record with 'uploaded' status
            $video = Video::create([
                'title' => $request->title,
                'description' => $request->description,
                'original_filename' => $originalName,
                'original_path' => $originalPath,
                'file_size' => $file->getSize(),
                'status' => 'uploaded',
                'user_id' => $user->id,
            ]);

            // Increment usage counter
            if ($user->activeSubscription) {
                $user->activeSubscription->incrementUsage('max_video_uploads_per_month');
            }

            // Dispatch video processing job
            Log::info("Dispatching video processing job for video ID: {$video->id}", [
                'video_title' => $video->title,
                'file_size' => $video->file_size,
                'original_path' => $video->original_path
            ]);

            // Update status to processing and dispatch the job
            $video->update(['status' => 'processing']);
            \App\Jobs\ProcessVideoJob::dispatch($video);

            return response()->json([
                'message' => 'Video uploaded successfully and is being processed',
                'video' => [
                    'id' => $video->id,
                    'title' => $video->title,
                    'description' => $video->description,
                    'status' => $video->status,
                    'file_size' => $video->file_size,
                    'original_path' => $video->original_path,
                    'created_at' => $video->created_at,
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error("Video upload failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id ?? 'not_authenticated'
            ]);

            return response()->json([
                'error' => 'Upload failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function stream(Video $video): JsonResponse
    {
        if ($video->status !== 'completed') {
            return response()->json([
                'error' => 'Video is not ready for streaming',
                'status' => $video->status
            ], 400);
        }

        // Log additional usage for video streaming (track video file size as data transfer)
        $apiKey = request()->attributes->get('api_key');
        if ($apiKey && $video->file_size) {
            \App\Models\ApiUsage::logUsage(
                $apiKey->id,
                'v1/videos/' . $video->id . '/stream-data',
                'STREAM',
                request()->ip(),
                request()->userAgent(),
                200,
                0, // No additional response time for metadata
                $video->file_size // Track the actual video file size as data transfer
            );
        }

        return response()->json([
            'hls_url' => $video->hls_path ? $video->hls_url : null,
            'thumbnail_url' => $video->thumbnail_path ? url('storage/' . $video->thumbnail_path) : null,
            'resolutions' => $video->resolutions,
            'duration' => $video->duration,
            'file_size' => $video->file_size, // Include file size in response for frontend tracking
        ]);
    }

    public function destroy(Request $request, Video $video): JsonResponse
    {
        // Check if the video belongs to the authenticated user
        $user = $request->user();
        if ($video->user_id !== $user->id) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You can only delete your own videos'
            ], 403);
        }

        try {
            // Delete video files from storage
            if ($video->original_path) {
                Storage::disk('public')->delete($video->original_path);
            }

            if ($video->hls_path) {
                // Delete HLS directory and all its contents
                $hlsDir = dirname($video->hls_path);
                Storage::disk('public')->deleteDirectory($hlsDir);
            }

            if ($video->thumbnail_path) {
                Storage::disk('public')->delete($video->thumbnail_path);
            }

            // Delete video record from database
            $video->delete();

            return response()->json([
                'message' => 'Video deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete video',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Dashboard methods (using Sanctum authentication)
    public function dashboardIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        $videos = Video::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($videos);
    }

    public function dashboardShow(Request $request, Video $video): JsonResponse
    {
        // Check if the video belongs to the authenticated user
        $user = $request->user();
        if ($video->user_id !== $user->id) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You can only view your own videos'
            ], 403);
        }

        return response()->json($video);
    }

    public function dashboardUpload(Request $request): JsonResponse
    {
        // Same as upload method but without API key checks
        return $this->upload($request);
    }

    public function dashboardDestroy(Request $request, Video $video): JsonResponse
    {
        // Same as destroy method
        return $this->destroy($request, $video);
    }
}
