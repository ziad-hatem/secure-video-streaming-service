<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Perfect for small projects and testing',
                'price' => 9.99,
                'features' => [
                    'API Access',
                    'Basic Video Streaming',
                    'Email Support',
                    'Standard Security Features',
                ],
                'limits' => [
                    'api_calls_per_month' => 10000,
                    'storage_gb' => 5,
                    'max_video_uploads_per_month' => 50,
                    'max_api_keys' => 3,
                    'max_video_duration_minutes' => 30,
                    'video_streams_per_month' => 1000,
                    'data_transfer_gb' => 10,
                ],
                'sort_order' => 1,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Ideal for growing businesses and applications',
                'price' => 29.99,
                'features' => [
                    'API Access',
                    'Advanced Video Streaming',
                    'Priority Email Support',
                    'Enhanced Security Features',
                    'Custom Player Branding',
                    'Analytics Dashboard',
                ],
                'limits' => [
                    'api_calls_per_month' => 100000,
                    'storage_gb' => 50,
                    'max_video_uploads_per_month' => 500,
                    'max_api_keys' => 10,
                    'max_video_duration_minutes' => 120,
                    'video_streams_per_month' => 10000,
                    'data_transfer_gb' => 100,
                ],
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'For large-scale applications with high demands',
                'price' => 99.99,
                'features' => [
                    'API Access',
                    'Premium Video Streaming',
                    '24/7 Phone & Email Support',
                    'Advanced Security Features',
                    'Custom Player Branding',
                    'Advanced Analytics',
                    'Custom Integrations',
                    'SLA Guarantee',
                    'Dedicated Account Manager',
                ],
                'limits' => [
                    'api_calls_per_month' => 1000000,
                    'storage_gb' => 500,
                    'max_video_uploads_per_month' => 5000,
                    'max_api_keys' => 50,
                    'max_video_duration_minutes' => 480,
                    'video_streams_per_month' => 100000,
                    'data_transfer_gb' => 1000,
                ],
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $planData) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }
    }
}
