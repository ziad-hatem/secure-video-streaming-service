<?php

namespace App\Console\Commands;

use App\Models\UserSubscription;
use Illuminate\Console\Command;

class CheckExpiredSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and update expired subscriptions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expired subscriptions...');

        $expiredCount = 0;

        // Get all active subscriptions that have an end date
        $subscriptions = UserSubscription::where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->get();

        foreach ($subscriptions as $subscription) {
            $subscription->update(['status' => 'expired']);
            $expiredCount++;

            $this->line("Expired subscription ID {$subscription->id} for user {$subscription->user->email}");
        }

        if ($expiredCount > 0) {
            $this->info("Updated {$expiredCount} expired subscription(s).");
        } else {
            $this->info('No expired subscriptions found.');
        }

        return Command::SUCCESS;
    }
}
