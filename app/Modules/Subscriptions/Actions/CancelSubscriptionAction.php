<?php

namespace App\Modules\Subscriptions\Actions;

use App\Modules\Lifecycle\Services\SubscriptionLifecycleManager;
use App\Modules\Subscriptions\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CancelSubscriptionAction
{
    public function __construct(
        private readonly SubscriptionLifecycleManager $lifecycleManager,
    ) {
    }

    public function execute(int $subscriptionId, string $reason = 'manual_cancellation'): Subscription
    {
        return DB::transaction(function () use ($subscriptionId, $reason): Subscription {
            $subscription = Subscription::query()
                ->with('plan', 'planPrice', 'user')
                ->lockForUpdate()
                ->findOrFail($subscriptionId);

            $this->lifecycleManager->cancel(
                subscription: $subscription,
                canceledAt: CarbonImmutable::now(),
                reason: $reason,
            );

            return $subscription->fresh(['user', 'plan', 'planPrice']);
        });
    }
}
