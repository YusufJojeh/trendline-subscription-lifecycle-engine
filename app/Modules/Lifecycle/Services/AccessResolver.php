<?php

namespace App\Modules\Lifecycle\Services;

use App\Modules\Subscriptions\Models\Subscription;
use App\Shared\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;

class AccessResolver
{
    public function isGranted(Subscription $subscription, ?CarbonImmutable $at = null): bool
    {
        $at ??= CarbonImmutable::now();

        return match ($subscription->status) {
            SubscriptionStatus::Trialing => $subscription->trial_ends_at !== null
                && $subscription->trial_ends_at->greaterThan($at),
            SubscriptionStatus::Active => $subscription->current_period_ends_at !== null
                && $subscription->current_period_ends_at->greaterThan($at),
            SubscriptionStatus::PastDue => $subscription->grace_period_ends_at !== null
                && $subscription->grace_period_ends_at->greaterThan($at),
            SubscriptionStatus::Canceled => false,
        };
    }

    public function explain(Subscription $subscription, ?CarbonImmutable $at = null): array
    {
        $at ??= CarbonImmutable::now();
        $granted = $this->isGranted($subscription, $at);

        return [
            'granted' => $granted,
            'reason' => $this->reason($subscription, $at, $granted),
            'checked_at' => $at->toIso8601String(),
            'status' => $subscription->status->value,
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'current_period_ends_at' => $subscription->current_period_ends_at?->toIso8601String(),
            'grace_period_ends_at' => $subscription->grace_period_ends_at?->toIso8601String(),
        ];
    }

    private function reason(Subscription $subscription, CarbonImmutable $at, bool $granted): string
    {
        return match ($subscription->status) {
            SubscriptionStatus::Trialing => $granted
                ? 'trial_active'
                : ($subscription->trial_ends_at === null ? 'trial_window_missing' : 'trial_expired_or_payment_required'),
            SubscriptionStatus::Active => $granted
                ? 'current_period_active'
                : ($subscription->current_period_ends_at === null ? 'current_period_missing' : 'current_period_expired'),
            SubscriptionStatus::PastDue => $granted
                ? 'grace_period_active'
                : ($subscription->grace_period_ends_at === null ? 'grace_period_missing' : 'grace_period_expired'),
            SubscriptionStatus::Canceled => 'subscription_canceled',
        };
    }
}
