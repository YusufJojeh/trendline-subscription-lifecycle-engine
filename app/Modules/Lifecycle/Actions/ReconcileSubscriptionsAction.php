<?php

namespace App\Modules\Lifecycle\Actions;

use App\Modules\Lifecycle\Services\SubscriptionLifecycleManager;
use App\Modules\Subscriptions\Models\Subscription;
use App\Shared\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReconcileSubscriptionsAction
{
    public function __construct(
        private readonly SubscriptionLifecycleManager $lifecycleManager,
    ) {
    }

    public function execute(?CarbonImmutable $reconciledAt = null): array
    {
        $reconciledAt ??= CarbonImmutable::now();

        $expiredTrials = 0;
        $expiredGracePeriods = 0;

        Subscription::query()
            ->where('status', SubscriptionStatus::Trialing->value)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', $reconciledAt)
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($reconciledAt, &$expiredTrials): void {
                foreach ($subscriptions as $subscription) {
                    DB::transaction(function () use ($subscription, $reconciledAt, &$expiredTrials): void {
                        $locked = Subscription::query()
                            ->whereKey($subscription->id)
                            ->lockForUpdate()
                            ->first();

                        if ($locked === null) {
                            return;
                        }

                        $before = $locked->status;

                        $this->lifecycleManager->reconcileExpiredTrial($locked, $reconciledAt);

                        if ($before !== $locked->fresh()->status) {
                            $expiredTrials++;
                        }
                    });
                }
            });

        Subscription::query()
            ->where('status', SubscriptionStatus::PastDue->value)
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<=', $reconciledAt)
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($reconciledAt, &$expiredGracePeriods): void {
                foreach ($subscriptions as $subscription) {
                    DB::transaction(function () use ($subscription, $reconciledAt, &$expiredGracePeriods): void {
                        $locked = Subscription::query()
                            ->whereKey($subscription->id)
                            ->lockForUpdate()
                            ->first();

                        if ($locked === null) {
                            return;
                        }

                        $before = $locked->status;

                        $this->lifecycleManager->reconcileExpiredGracePeriod($locked, $reconciledAt);

                        if ($before !== $locked->fresh()->status) {
                            $expiredGracePeriods++;
                        }
                    });
                }
            });

        return [
            'reconciled_at' => $reconciledAt->toIso8601String(),
            'expired_trials_canceled' => $expiredTrials,
            'expired_grace_periods_canceled' => $expiredGracePeriods,
        ];
    }
}
