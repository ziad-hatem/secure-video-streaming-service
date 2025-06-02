<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\ApiUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApiKeyController extends Controller
{
    /**
     * Get all API keys for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $apiKeys = $user->apiKeys()
                       ->select(['id', 'name', 'key', 'permissions', 'last_used_at', 'expires_at', 'is_active', 'created_at'])
                       ->orderBy('created_at', 'desc')
                       ->get()
                       ->map(function ($key) {
                           $key->masked_key = $key->getMaskedKeyAttribute();
                           unset($key->key); // Remove actual key from response
                           return $key;
                       });

        return response()->json([
            'api_keys' => $apiKeys
        ]);
    }

    /**
     * Create a new API key
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Check if user has reached API key limit
        $keyCount = $user->apiKeys()->where('is_active', true)->count();
        $maxKeys = $user->getCurrentPlan()?->getLimit('max_api_keys', 5) ?? 5;

        if ($keyCount >= $maxKeys) {
            return response()->json([
                'error' => 'API key limit reached',
                'message' => "You can only have {$maxKeys} active API keys"
            ], 403);
        }

        $apiKey = ApiKey::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'key' => ApiKey::generateKey(),
            'permissions' => $request->permissions ?? ['*'], // Default to all permissions
            'expires_at' => $request->expires_at,
        ]);

        return response()->json([
            'message' => 'API key created successfully',
            'api_key' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'key' => $apiKey->key, // Show full key only on creation
                'permissions' => $apiKey->permissions,
                'expires_at' => $apiKey->expires_at,
                'created_at' => $apiKey->created_at,
            ]
        ], 201);
    }

    /**
     * Get API key details with usage statistics
     */
    public function show(Request $request, ApiKey $apiKey): JsonResponse
    {
        // Ensure the API key belongs to the authenticated user
        if ($apiKey->user_id !== $request->user()->id) {
            return response()->json(['error' => 'API key not found'], 404);
        }

        $period = $request->query('period', 'month');
        $usage = ApiUsage::getUsageStats($apiKey->id, $period);

        return response()->json([
            'api_key' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'masked_key' => $apiKey->getMaskedKeyAttribute(),
                'permissions' => $apiKey->permissions,
                'last_used_at' => $apiKey->last_used_at,
                'expires_at' => $apiKey->expires_at,
                'is_active' => $apiKey->is_active,
                'created_at' => $apiKey->created_at,
            ],
            'usage' => $usage
        ]);
    }

    /**
     * Update API key
     */
    public function update(Request $request, ApiKey $apiKey): JsonResponse
    {
        // Ensure the API key belongs to the authenticated user
        if ($apiKey->user_id !== $request->user()->id) {
            return response()->json(['error' => 'API key not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string',
            'expires_at' => 'sometimes|nullable|date|after:now',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $apiKey->update($request->only(['name', 'permissions', 'expires_at', 'is_active']));

        return response()->json([
            'message' => 'API key updated successfully',
            'api_key' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'masked_key' => $apiKey->getMaskedKeyAttribute(),
                'permissions' => $apiKey->permissions,
                'expires_at' => $apiKey->expires_at,
                'is_active' => $apiKey->is_active,
                'updated_at' => $apiKey->updated_at,
            ]
        ]);
    }

    /**
     * Regenerate API key
     */
    public function regenerate(Request $request, ApiKey $apiKey): JsonResponse
    {
        // Ensure the API key belongs to the authenticated user
        if ($apiKey->user_id !== $request->user()->id) {
            return response()->json(['error' => 'API key not found'], 404);
        }

        $newKey = ApiKey::generateKey();
        $apiKey->update(['key' => $newKey]);

        return response()->json([
            'message' => 'API key regenerated successfully',
            'api_key' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'key' => $newKey, // Show full key after regeneration
                'permissions' => $apiKey->permissions,
                'expires_at' => $apiKey->expires_at,
                'is_active' => $apiKey->is_active,
                'updated_at' => $apiKey->updated_at,
            ]
        ]);
    }

    /**
     * Delete API key
     */
    public function destroy(Request $request, ApiKey $apiKey): JsonResponse
    {
        // Ensure the API key belongs to the authenticated user
        if ($apiKey->user_id !== $request->user()->id) {
            return response()->json(['error' => 'API key not found'], 404);
        }

        $apiKey->delete();

        return response()->json([
            'message' => 'API key deleted successfully'
        ]);
    }

    /**
     * Get usage statistics for all user's API keys
     */
    public function usage(Request $request): JsonResponse
    {
        $user = $request->user();
        $period = $request->query('period', 'month');

        $apiKeys = $user->apiKeys()->get();
        $totalUsage = [
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'total_bytes' => 0,
            'avg_response_time' => 0,
        ];

        $keyUsage = [];
        $totalResponseTimes = [];

        foreach ($apiKeys as $apiKey) {
            $usage = ApiUsage::getUsageStats($apiKey->id, $period);
            $keyUsage[] = [
                'api_key_id' => $apiKey->id,
                'api_key_name' => $apiKey->name,
                'usage' => $usage
            ];

            $totalUsage['total_requests'] += $usage['total_requests'];
            $totalUsage['successful_requests'] += $usage['successful_requests'];
            $totalUsage['failed_requests'] += $usage['failed_requests'];
            $totalUsage['total_bytes'] += $usage['total_bytes'];

            if ($usage['avg_response_time'] > 0) {
                $totalResponseTimes[] = $usage['avg_response_time'];
            }
        }

        $totalUsage['avg_response_time'] = count($totalResponseTimes) > 0
            ? array_sum($totalResponseTimes) / count($totalResponseTimes)
            : 0;

        return response()->json([
            'period' => $period,
            'total_usage' => $totalUsage,
            'api_keys_usage' => $keyUsage
        ]);
    }
}
