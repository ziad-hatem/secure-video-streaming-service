<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

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

        // Debug logging
        Log::info('Fetching subscription for user: ' . $user->id);

        // Load the active subscription with its plan
        $user->load(['activeSubscription.plan']);
        $subscription = $user->activeSubscription;

        // Additional debugging - check all subscriptions
        $allSubscriptions = $user->subscriptions()->with('plan')->get();
        Log::info('All subscriptions for user ' . $user->id . ':', $allSubscriptions->toArray());

        if (!$subscription) {
            Log::info('No active subscription found for user: ' . $user->id);
            
            return response()->json([
                'subscription' => null,
                'trial_ends_at' => $user->trial_ends_at,
                'is_on_trial' => $user->isOnTrial()
            ]);
        }

        Log::info('Active subscription found:', $subscription->toArray());

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
     * Subscribe to a plan (create Stripe checkout session)
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
        $existingSubscription = $user->activeSubscription;
        if ($existingSubscription) {
            return response()->json([
                'error' => 'Already subscribed',
                'message' => 'You already have an active subscription. Use the change plan endpoint to modify your subscription.'
            ], 400);
        }

        try {
            // Create Stripe checkout session
            $checkoutData = $this->stripeService->createCheckoutSession($user, $plan);

            return response()->json([
                'message' => 'Checkout session created successfully',
                'checkout_url' => $checkoutData['checkout_url'],
                'session_id' => $checkoutData['session_id'],
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe checkout error: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Payment processing failed',
                'message' => $e->getMessage()
            ], 500);
        }
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
        
        // Load the active subscription
        $user->load('activeSubscription');
        $currentSubscription = $user->activeSubscription;

        if (!$currentSubscription) {
            return response()->json([
                'error' => 'No active subscription',
                'message' => 'You need to have an active subscription to change plans'
            ], 400);
        }

        if ($currentSubscription->plan_id == $newPlan->id) {
            return response()->json([
                'error' => 'Same plan',
                'message' => 'You are already subscribed to this plan'
            ], 400);
        }

        try {
            // If using Stripe, update the subscription there first
            if ($currentSubscription->stripe_subscription_id) {
                $this->stripeService->changePlan($currentSubscription->stripe_subscription_id, $newPlan);
            }

            // Update the current subscription to the new plan
            $currentSubscription->update([
                'plan_id' => $newPlan->id,
                'usage_stats' => [], // Reset usage stats when changing plans
            ]);

            // Reload the subscription with the new plan
            $currentSubscription->load('plan');

            return response()->json([
                'message' => 'Plan changed successfully',
                'subscription' => [
                    'id' => $currentSubscription->id,
                    'plan' => $currentSubscription->plan,
                    'status' => $currentSubscription->status,
                    'starts_at' => $currentSubscription->starts_at,
                    'ends_at' => $currentSubscription->ends_at,
                    'updated_at' => $currentSubscription->fresh()->updated_at,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Plan change error: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to change plan',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel subscription
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('activeSubscription');
        $subscription = $user->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'error' => 'No active subscription',
                'message' => 'You do not have an active subscription to cancel'
            ], 400);
        }

        try {
            // If using Stripe, cancel the subscription there first
            if ($subscription->stripe_subscription_id) {
                $this->stripeService->cancelSubscription($subscription->stripe_subscription_id);
            }

            // Update the subscription status
            $subscription->update([
                'status' => 'cancelled',
                'ends_at' => now(), // End immediately, or you could set it to end of billing period
            ]);

            return response()->json([
                'message' => 'Subscription cancelled successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Subscription cancellation error: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to cancel subscription',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subscription usage statistics
     */
    public function usage(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['activeSubscription.plan']);
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

    /**
     * Handle successful Stripe checkout
     */
    public function handleCheckoutSuccess(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $subscription = $this->stripeService->handleSuccessfulCheckout($request->session_id);

            return response()->json([
                'message' => 'Subscription activated successfully',
                'subscription' => [
                    'id' => $subscription->id,
                    'plan' => $subscription->plan,
                    'status' => $subscription->status,
                    'starts_at' => $subscription->starts_at,
                    'ends_at' => $subscription->ends_at,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Checkout success handling error: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to activate subscription',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Stripe configuration for frontend
     */
    public function getStripeConfig(): JsonResponse
    {
        return response()->json([
            'publishable_key' => $this->stripeService->getPublishableKey(),
        ]);
    }

    /**
     * Manually create a subscription (for testing or admin purposes)
     */
    public function createManualSubscription(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'plan_id' => 'required|exists:subscription_plans,id',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $subscription = UserSubscription::create([
                'user_id' => $request->user_id,
                'plan_id' => $request->plan_id,
                'status' => 'active',
                'starts_at' => $request->starts_at ?? now(),
                'ends_at' => $request->ends_at,
                'usage_stats' => [],
            ]);

            $subscription->load('plan');

            return response()->json([
                'message' => 'Subscription created successfully',
                'subscription' => $subscription
            ]);
        } catch (\Exception $e) {
            Log::error('Manual subscription creation error: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to create subscription',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}