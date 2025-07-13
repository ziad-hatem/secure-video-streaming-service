<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'account_type' => 'sometimes|in:individual,business',
            'company_name' => 'required_if:account_type,business|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'account_type' => $request->account_type ?? 'individual',
            'company_name' => $request->company_name,
            'trial_ends_at' => now()->addDays(14), // 14-day trial
            'is_active' => true,
        ]);

        // Create dashboard API key for new user
        $dashboardApiKey = $user->apiKeys()->create([
            'name' => 'Dashboard Access',
            'key' => ApiKey::generateKey(),
            'permissions' => ['*'], // Full permissions for dashboard
            'is_active' => true,
            'expires_at' => null, // No expiration for dashboard keys
        ]);

        $token = $dashboardApiKey->key;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token,
            'subscription' => null,
        ], 201);
    }

    /**
     * Login user
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'error' => 'Invalid credentials',
                'message' => 'The provided credentials are incorrect.'
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        if (!$user->is_active) {
            return response()->json([
                'error' => 'Account suspended',
                'message' => 'Your account has been suspended. Please contact support.'
            ], 403);
        }

        // Revoke all existing tokens
        $user->tokens()->delete();

        // Create Sanctum token for web dashboard authentication
        $token = $user->createToken('dashboard-access')->plainTextToken;

        // Also create or get dashboard API key for API access
        $dashboardApiKey = $user->apiKeys()->where('name', 'Dashboard Access')->first();

        if (!$dashboardApiKey) {
            // Create new dashboard API key
            $dashboardApiKey = $user->apiKeys()->create([
                'name' => 'Dashboard Access',
                'key' => \App\Models\ApiKey::generateKey(),
                'permissions' => ['*'], // Full permissions for dashboard
                'is_active' => true,
                'expires_at' => null, // No expiration for dashboard keys
            ]);
        } else {
            // Reactivate existing key if needed
            $dashboardApiKey->update([
                'is_active' => true,
                'last_used_at' => now(),
            ]);
        }

        // Load subscription data
        $user->load('activeSubscription.plan');

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token, // Sanctum token for web dashboard
            'api_key' => $dashboardApiKey->key, // API key for API access
            'subscription' => $user->activeSubscription ? [
                'id' => $user->activeSubscription->id,
                'plan' => $user->activeSubscription->plan,
                'status' => $user->activeSubscription->status,
                'starts_at' => $user->activeSubscription->starts_at,
                'ends_at' => $user->activeSubscription->ends_at,
                'usage_stats' => $user->activeSubscription->usage_stats,
            ] : null,
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the current Sanctum token
        $request->user()->currentAccessToken()->delete();

        // Also deactivate the dashboard API key if it exists
        $dashboardApiKey = $request->user()->apiKeys()->where('name', 'Dashboard Access')->first();
        if ($dashboardApiKey) {
            $dashboardApiKey->update(['is_active' => false]);
        }

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('activeSubscription.plan');

        return response()->json([
            'user' => $user,
            'subscription' => $user->activeSubscription ? [
                'id' => $user->activeSubscription->id,
                'plan' => $user->activeSubscription->plan,
                'status' => $user->activeSubscription->status,
                'starts_at' => $user->activeSubscription->starts_at,
                'ends_at' => $user->activeSubscription->ends_at,
                'usage_stats' => $user->activeSubscription->usage_stats,
            ] : null,
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'error' => 'Invalid password',
                'message' => 'The current password is incorrect.'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        // Revoke all tokens to force re-login
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password changed successfully. Please login again.'
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $request->user()->id,
            'account_type' => 'sometimes|in:individual,business',
            'company_name' => 'required_if:account_type,business|string|max:255',
            'billing_email' => 'sometimes|nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $user->update($request->only([
            'name',
            'email',
            'account_type',
            'company_name',
            'billing_email'
        ]));

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh()
        ]);
    }
}
