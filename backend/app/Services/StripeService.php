<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Price;
use Stripe\Product;
use Stripe\Subscription;
use Exception;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret_key'));
    }

    /**
     * Create a Stripe checkout session for subscription
     */
    public function createCheckoutSession(User $user, SubscriptionPlan $plan): array
    {
        try {
            // Create or get Stripe customer
            $customer = $this->createOrGetCustomer($user);

            // Create or get Stripe price for the plan
            $price = $this->createOrGetPrice($plan);

            // Create checkout session
            $session = Session::create([
                'customer' => $customer->id,
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price' => $price->id,
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'subscription',
                'success_url' => env('FRONTEND_URL', 'http://localhost:3000') . '/dashboard/subscription?success=true&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => env('FRONTEND_URL', 'http://localhost:3000') . '/dashboard/subscription?canceled=true',
                'metadata' => [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                ],
                'subscription_data' => [
                    'metadata' => [
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                    ],
                ],
            ]);

            return [
                'checkout_url' => $session->url,
                'session_id' => $session->id,
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to create checkout session: ' . $e->getMessage());
        }
    }

    /**
     * Create or get Stripe customer
     */
    private function createOrGetCustomer(User $user): Customer
    {
        if ($user->stripe_customer_id) {
            try {
                return Customer::retrieve($user->stripe_customer_id);
            } catch (Exception $e) {
                // Customer doesn't exist, create new one
            }
        }

        $customer = Customer::create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => $user->id,
            ],
        ]);

        // Save customer ID to user
        $user->update(['stripe_customer_id' => $customer->id]);

        return $customer;
    }

    /**
     * Create or get Stripe price for plan
     */
    private function createOrGetPrice(SubscriptionPlan $plan): Price
    {
        if ($plan->stripe_price_id) {
            try {
                return Price::retrieve($plan->stripe_price_id);
            } catch (Exception $e) {
                // Price doesn't exist, create new one
            }
        }

        // Create product first
        $product = $this->createOrGetProduct($plan);

        // Create price
        $price = Price::create([
            'product' => $product->id,
            'unit_amount' => $plan->price * 100, // Convert to cents
            'currency' => 'usd',
            'recurring' => [
                'interval' => 'month',
            ],
            'metadata' => [
                'plan_id' => $plan->id,
            ],
        ]);

        // Save price ID to plan
        $plan->update(['stripe_price_id' => $price->id]);

        return $price;
    }

    /**
     * Create or get Stripe product for plan
     */
    private function createOrGetProduct(SubscriptionPlan $plan): Product
    {
        if ($plan->stripe_product_id) {
            try {
                return Product::retrieve($plan->stripe_product_id);
            } catch (Exception $e) {
                // Product doesn't exist, create new one
            }
        }

        $product = Product::create([
            'name' => $plan->name,
            'description' => $plan->description,
            'metadata' => [
                'plan_id' => $plan->id,
            ],
        ]);

        // Save product ID to plan
        $plan->update(['stripe_product_id' => $product->id]);

        return $product;
    }

    /**
     * Handle successful checkout session
     */
    public function handleSuccessfulCheckout(string $sessionId): UserSubscription
    {
        try {
            $session = Session::retrieve($sessionId);
            $subscription = Subscription::retrieve($session->subscription);

            $userId = $session->metadata->user_id;
            $planId = $session->metadata->plan_id;

            $user = User::findOrFail($userId);
            $plan = SubscriptionPlan::findOrFail($planId);

            // Cancel existing active subscription
            if ($user->hasActiveSubscription()) {
                $user->activeSubscription->update(['status' => 'cancelled']);
            }

            // Create new subscription
            $userSubscription = UserSubscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'stripe_subscription_id' => $subscription->id,
                'status' => 'active',
                'starts_at' => now(), // Start immediately
                'ends_at' => now()->addMonth(),
                'usage_stats' => [
                    'api_calls_per_month' => 0,
                    'video_streams_per_month' => 0,
                    'data_transfer_gb' => 0,
                    'max_video_uploads_per_month' => 0,
                ],
            ]);

            return $userSubscription;
        } catch (Exception $e) {
            throw new Exception('Failed to handle successful checkout: ' . $e->getMessage());
        }
    }

    /**
     * Change subscription plan
     */
    public function changePlan(string $stripeSubscriptionId, SubscriptionPlan $newPlan): void
    {
        try {
            // Get or create the new price for the plan
            $newPrice = $this->createOrGetPrice($newPlan);

            // Retrieve the current subscription
            $stripeSubscription = Subscription::retrieve($stripeSubscriptionId);

            // Update the subscription to use the new price
            Subscription::update($stripeSubscriptionId, [
                'items' => [
                    [
                        'id' => $stripeSubscription->items->data[0]->id,
                        'price' => $newPrice->id,
                    ],
                ],
                'proration_behavior' => 'create_prorations',
            ]);
        } catch (Exception $e) {
            throw new Exception('Failed to change plan in Stripe: ' . $e->getMessage());
        }
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(string $stripeSubscriptionId): void
    {
        try {
            $stripeSubscription = Subscription::retrieve($stripeSubscriptionId);
            $stripeSubscription->cancel();
        } catch (Exception $e) {
            throw new Exception('Failed to cancel subscription: ' . $e->getMessage());
        }
    }

    /**
     * Get Stripe publishable key
     */
    public function getPublishableKey(): string
    {
        return config('services.stripe.publishable_key');
    }
}
