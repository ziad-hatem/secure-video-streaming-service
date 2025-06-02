<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    /**
     * Get all available subscription plans
     */
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::active()->get();

        return response()->json([
            'plans' => $plans
        ]);
    }

    /**
     * Get user's current subscription
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'subscription' => null,
                'trial_ends_at' => $user->trial_ends_at,
                'is_on_trial' => $user->isOnTrial()
            ]);
        }

        return response()->json([
            'subscription' => [
                'id' => $subscription->id,
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->ends_at,
                'usage_stats' => $subscription->usage_stats,
                'created_at' => $subscription->created_at,
            ],
            'trial_ends_at' => $user->trial_ends_at,
            'is_on_trial' => $user->isOnTrial()
        ]);
    }

    /**
     * Subscribe to a plan
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        // Check if user already has an active subscription
        if ($user->hasActiveSubscription()) {
            return response()->json([
                'error' => 'Already subscribed',
                'message' => 'You already have an active subscription. Please cancel it first or upgrade instead.'
            ], 400);
        }

        // Create new subscription
        $subscription = UserSubscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(), // Monthly billing
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Successfully subscribed to plan',
            'subscription' => [
                'id' => $subscription->id,
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->ends_at,
                'created_at' => $subscription->created_at,
            ]
        ], 201);
    }

    /**
     * Upgrade/downgrade subscription
     */
    public function changePlan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $newPlan = SubscriptionPlan::findOrFail($request->plan_id);
        $currentSubscription = $user->activeSubscription;

        if (!$currentSubscription) {
            return response()->json([
                'error' => 'No active subscription',
                'message' => 'You need to have an active subscription to change plans'
            ], 400);
        }

        // Update the current subscription to the new plan
        $currentSubscription->update([
            'plan_id' => $newPlan->id,
            'usage_stats' => [], // Reset usage stats when changing plans
        ]);

        return response()->json([
            'message' => 'Plan changed successfully',
            'subscription' => [
                'id' => $currentSubscription->id,
                'plan' => $currentSubscription->fresh()->plan,
                'status' => $currentSubscription->status,
                'starts_at' => $currentSubscription->starts_at,
                'ends_at' => $currentSubscription->ends_at,
                'updated_at' => $currentSubscription->updated_at,
            ]
        ]);
    }

    /**
     * Cancel subscription
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'error' => 'No active subscription',
                'message' => 'You do not have an active subscription to cancel'
            ], 400);
        }

        $subscription->update([
            'status' => 'cancelled',
            'ends_at' => now(), // End immediately or you could set it to end of billing period
        ]);

        return response()->json([
            'message' => 'Subscription cancelled successfully'
        ]);
    }

    /**
     * Get subscription usage statistics
     */
    public function usage(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'error' => 'No active subscription',
                'message' => 'You do not have an active subscription'
            ], 400);
        }

        $plan = $subscription->plan;
        $usage = $subscription->usage_stats ?? [];
        $limits = $plan->limits ?? [];

        $usageData = [];
        foreach ($limits as $metric => $limit) {
            $current = $usage[$metric] ?? 0;
            $usageData[$metric] = [
                'current' => $current,
                'limit' => $limit,
                'percentage' => $limit > 0 ? round(($current / $limit) * 100, 2) : 0,
                'exceeded' => $current >= $limit,
            ];
        }

        return response()->json([
            'plan' => [
                'name' => $plan->name,
                'limits' => $limits,
            ],
            'usage' => $usageData,
            'billing_period' => [
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->ends_at,
            ]
        ]);
    }
}
