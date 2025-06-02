<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use App\Models\ApiUsage;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ApiUsageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $apiKeys = ApiKey::all();

        if ($apiKeys->isEmpty()) {
            $this->command->info('No API keys found. Please run TestUserSeeder first.');
            return;
        }

        $endpoints = [
            '/v1/videos',
            '/v1/videos/upload',
            '/v1/videos/1',
            '/v1/videos/2',
            '/v1/videos/1/stream',
            '/v1/user',
        ];

        $methods = ['GET', 'POST', 'PUT', 'DELETE'];
        $statusCodes = [200, 201, 400, 401, 404, 500];
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            'curl/7.68.0',
            'PostmanRuntime/7.28.4',
        ];

        foreach ($apiKeys as $apiKey) {
            // Generate usage data for the last 30 days
            for ($i = 0; $i < 30; $i++) {
                $date = Carbon::now()->subDays($i);
                $requestsPerDay = rand(10, 100);

                for ($j = 0; $j < $requestsPerDay; $j++) {
                    $endpoint = $endpoints[array_rand($endpoints)];
                    $method = $methods[array_rand($methods)];
                    $statusCode = $statusCodes[array_rand($statusCodes)];
                    
                    // Weight towards successful requests
                    if (rand(1, 100) <= 85) {
                        $statusCode = rand(1, 100) <= 90 ? 200 : 201;
                    }

                    $usage = new ApiUsage([
                        'api_key_id' => $apiKey->id,
                        'endpoint' => $endpoint,
                        'method' => $method,
                        'ip_address' => $this->generateRandomIP(),
                        'user_agent' => $userAgents[array_rand($userAgents)],
                        'response_status' => $statusCode,
                        'response_time_ms' => rand(50, 2000),
                        'bytes_transferred' => rand(1024, 1048576), // 1KB to 1MB
                    ]);

                    // Set custom created_at timestamp
                    $randomTime = $date->copy()->subMinutes(rand(0, 1439));
                    $usage->created_at = $randomTime;
                    $usage->updated_at = $randomTime;
                    $usage->save();
                }
            }
        }

        $this->command->info('API usage data seeded successfully!');
    }

    private function generateRandomIP(): string
    {
        return rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255);
    }
}
