<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a test user
        $user = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
                'account_type' => 'business',
                'company_name' => 'Test Company',
                'billing_email' => 'billing@example.com',
                'trial_ends_at' => now()->addDays(14), // 14-day trial
                'is_active' => true,
            ]
        );

        // Subscribe to Professional plan
        $professionalPlan = SubscriptionPlan::where('slug', 'professional')->first();
        if ($professionalPlan) {
            UserSubscription::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'user_id' => $user->id,
                    'plan_id' => $professionalPlan->id,
                    'starts_at' => now(),
                    'ends_at' => now()->addMonth(),
                    'status' => 'active',
                    'usage_stats' => [
                        'api_calls_per_month' => 0,
                        'max_video_uploads_per_month' => 0,
                    ],
                ]
            );
        }

        // Create API keys for the user
        $apiKeys = [
            [
                'name' => 'Production API Key',
                'permissions' => ['*'], // All permissions
                'expires_at' => null, // Never expires
            ],
            [
                'name' => 'Development API Key',
                'permissions' => ['videos.read', 'videos.write'],
                'expires_at' => now()->addYear(),
            ],
            [
                'name' => 'Read-only API Key',
                'permissions' => ['videos.read'],
                'expires_at' => now()->addMonths(6),
            ],
        ];

        foreach ($apiKeys as $keyData) {
            ApiKey::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => $keyData['name']
                ],
                [
                    'user_id' => $user->id,
                    'name' => $keyData['name'],
                    'key' => ApiKey::generateKey(),
                    'permissions' => $keyData['permissions'],
                    'expires_at' => $keyData['expires_at'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Test user created with email: test@example.com and password: password');
        $this->command->info('API keys created for the test user');
    }
}
