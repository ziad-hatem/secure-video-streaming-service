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

        // If user is authenticated, check subscription limits
        if ($user) {
            // Check if user has an active subscription or is on trial
            if (!$user->isOnTrial() && !$user->hasActiveSubscription()) {
                return response()->json([
                    'error' => 'No active subscription',
                    'message' => 'Please subscribe to a plan to access videos'
                ], 402); // Payment Required
            }

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
        // Check subscription limits for authenticated users
        $user = request()->user();
        if ($user) {
            // Check if user has an active subscription or is on trial
            if (!$user->isOnTrial() && !$user->hasActiveSubscription()) {
                return response()->json([
                    'error' => 'No active subscription',
                    'message' => 'Please subscribe to a plan to access video details'
                ], 402); // Payment Required
            }
        }

        $video->load('user');
        return response()->json($video);
    }

    public function upload(Request $request): JsonResponse
    {
        // Check if the request has a file
        if (!$request->hasFile('video')) {
            return response()->json([
                'error' => 'No file uploaded',
                'message' => 'Please select a video file to upload'
            ], 422);
        }

        // Check for upload errors
        $file = $request->file('video');
        if ($file->getError() !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive',
                UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
            ];

            $errorMessage = $errorMessages[$file->getError()] ?? 'Unknown upload error';

            return response()->json([
                'error' => 'Upload failed',
                'message' => $errorMessage,
                'php_limits' => [
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size'),
                    'max_execution_time' => ini_get('max_execution_time'),
                ]
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video' => 'required|file|mimes:mp4,avi,mov,wmv,flv|max:1048576', // 1GB max (1024*1024 KB)
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
                'file_size_mb' => round($file->getSize() / (1024 * 1024), 2),
                'max_allowed_mb' => 1024
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

        // Check subscription limits before allowing video streaming
        $user = request()->user();
        if ($user) {
            // Check if user has exceeded video streaming limits
            if ($user->hasExceededUsageLimit('video_streams_per_month')) {
                return response()->json([
                    'error' => 'Video streaming limit exceeded',
                    'message' => 'You have exceeded your monthly video streaming limit. Please upgrade your plan or wait for the next billing cycle.'
                ], 429); // Too Many Requests
            }

            // Check if user has exceeded data transfer limits
            if ($user->hasExceededUsageLimit('data_transfer_gb')) {
                return response()->json([
                    'error' => 'Data transfer limit exceeded',
                    'message' => 'You have exceeded your monthly data transfer limit. Please upgrade your plan or wait for the next billing cycle.'
                ], 429); // Too Many Requests
            }

            // Check if adding this video's file size would exceed data transfer limit
            $subscription = $user->activeSubscription;
            if ($subscription && $video->file_size) {
                $currentDataTransfer = $subscription->getCurrentUsage('data_transfer_gb');
                $videoSizeGB = $video->file_size / (1024 * 1024 * 1024); // Convert bytes to GB
                $dataTransferLimit = $subscription->plan->getLimit('data_transfer_gb');

                if ($dataTransferLimit && ($currentDataTransfer + $videoSizeGB) > $dataTransferLimit) {
                    return response()->json([
                        'error' => 'Data transfer limit would be exceeded',
                        'message' => 'Streaming this video would exceed your monthly data transfer limit. Please upgrade your plan.'
                    ], 429); // Too Many Requests
                }
            }
        }

        // Increment usage counters for video streaming (but not data transfer - that's tracked per chunk)
        if ($user) {
            // Reload the user with active subscription to ensure it's available
            $user->load('activeSubscription');

            if ($user->activeSubscription) {
                $user->activeSubscription->incrementUsage('video_streams_per_month');
            }
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
