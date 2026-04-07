<?php

namespace App\Modules\Lifecycle\Services;

use App\Modules\Audit\Models\OutboxMessage;
use App\Modules\Lifecycle\Events\SubscriptionActivated;
use App\Modules\Lifecycle\Events\SubscriptionCanceled;
use App\Modules\Lifecycle\Events\SubscriptionLifecycleEvent;
use App\Modules\Lifecycle\Events\SubscriptionMovedToPastDue;
use App\Modules\Lifecycle\Events\SubscriptionPaymentFailed;
use App\Modules\Lifecycle\Events\SubscriptionStarted;
use App\Modules\Lifecycle\Events\TrialExpired;
use App\Modules\Subscriptions\Models\Subscription;
use App\Shared\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionLifecycleManager
{
    private const GRACE_PERIOD_DAYS = 3;

    public function start(Subscription $subscription, CarbonImmutable $startedAt): Subscription
    {
        $subscription->loadMissing('plan', 'planPrice');

        $hasTrial = $subscription->plan->trial_days > 0;
        // Unpaid starts stay `trialing`; trial_days=0 → trial_ends_at == starts_at (no access until paid).
        $trialEndsAt = $hasTrial
            ? $startedAt->addDays($subscription->plan->trial_days)
            : $startedAt;

        $subscription->fill([
            'status' => SubscriptionStatus::Trialing,
            'starts_at' => $startedAt,
            'trial_ends_at' => $trialEndsAt,
            'current_period_starts_at' => null,
            'current_period_ends_at' => null,
            'grace_period_ends_at' => null,
            'canceled_at' => null,
            'ended_at' => null,
        ])->save();

        $this->emit(new SubscriptionStarted(
            subscription: $subscription->fresh(['plan', 'planPrice']),
            fromStatus: null,
            toStatus: SubscriptionStatus::Trialing,
            reason: 'subscription_started',
            occurredAt: $startedAt,
            metadata: [
                'trial_days' => $subscription->plan->trial_days,
                'trial_behavior' => $hasTrial ? 'trial_granted' : 'payment_required_immediately',
                'billing_cycle' => $subscription->planPrice->billing_cycle->value,
                'currency' => $subscription->planPrice->currency->value,
            ],
        ));

        return $subscription;
    }

    public function activate(Subscription $subscription, CarbonImmutable $paidAt, string $reason = 'payment_succeeded', array $metadata = []): Subscription
    {
        $subscription->loadMissing('planPrice');

        if ($subscription->status === SubscriptionStatus::Canceled) {
            throw ValidationException::withMessages([
                'subscription' => ['Cannot record a payment that would reactivate a canceled subscription.'],
            ]);
        }

        $anchor = $subscription->current_period_ends_at !== null && $subscription->current_period_ends_at->greaterThan($paidAt)
            ? $subscription->current_period_ends_at
            : $paidAt;

        $fromStatus = $subscription->status;

        $subscription->fill([
            'status' => SubscriptionStatus::Active,
            'current_period_starts_at' => $anchor,
            'current_period_ends_at' => CarbonImmutable::instance(
                $subscription->planPrice->billing_cycle->addTo($anchor)
            ),
            'grace_period_ends_at' => null,
            'ended_at' => null,
        ])->save();

        $this->emit(new SubscriptionActivated(
            subscription: $subscription->fresh(['plan', 'planPrice']),
            fromStatus: $fromStatus,
            toStatus: SubscriptionStatus::Active,
            reason: $reason,
            occurredAt: $paidAt,
            metadata: $metadata + [
                'billing_cycle' => $subscription->planPrice->billing_cycle->value,
            ],
        ));

        return $subscription;
    }

    public function markPaymentFailed(Subscription $subscription, CarbonImmutable $failedAt, array $metadata = []): Subscription
    {
        if ($subscription->status !== SubscriptionStatus::Active) {
            return $subscription;
        }

        $this->emit(new SubscriptionPaymentFailed(
            subscription: $subscription->fresh(['plan', 'planPrice']),
            fromStatus: $subscription->status,
            toStatus: $subscription->status,
            reason: 'payment_failed',
            occurredAt: $failedAt,
            metadata: $metadata,
        ));

        $fromStatus = $subscription->status;

        $subscription->fill([
            'status' => SubscriptionStatus::PastDue,
            'grace_period_ends_at' => $failedAt->addDays(self::GRACE_PERIOD_DAYS),
        ])->save();

        $this->emit(new SubscriptionMovedToPastDue(
            subscription: $subscription->fresh(['plan', 'planPrice']),
            fromStatus: $fromStatus,
            toStatus: SubscriptionStatus::PastDue,
            reason: 'payment_failed_grace_period_started',
            occurredAt: $failedAt,
            metadata: $metadata + [
                'grace_period_ends_at' => $subscription->grace_period_ends_at?->toIso8601String(),
            ],
        ));

        return $subscription;
    }

    public function cancel(Subscription $subscription, CarbonImmutable $canceledAt, string $reason, array $metadata = []): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Canceled) {
            return $subscription;
        }

        $fromStatus = $subscription->status;

        $subscription->fill([
            'status' => SubscriptionStatus::Canceled,
            'grace_period_ends_at' => null,
            'canceled_at' => $subscription->canceled_at ?? $canceledAt,
            'ended_at' => $canceledAt,
        ])->save();

        $this->emit(new SubscriptionCanceled(
            subscription: $subscription->fresh(['plan', 'planPrice']),
            fromStatus: $fromStatus,
            toStatus: SubscriptionStatus::Canceled,
            reason: $reason,
            occurredAt: $canceledAt,
            metadata: $metadata,
        ));

        return $subscription;
    }

    public function reconcileExpiredTrial(Subscription $subscription, CarbonImmutable $reconciledAt): Subscription
    {
        if (
            $subscription->status !== SubscriptionStatus::Trialing
            || $subscription->trial_ends_at === null
            || $subscription->trial_ends_at->isFuture()
        ) {
            return $subscription;
        }

        $this->emit(new TrialExpired(
            subscription: $subscription->fresh(['plan', 'planPrice']),
            fromStatus: SubscriptionStatus::Trialing,
            toStatus: SubscriptionStatus::Trialing,
            reason: 'trial_expired',
            occurredAt: $reconciledAt,
            metadata: [
                'trial_ended_at' => $subscription->trial_ends_at->toIso8601String(),
            ],
        ));

        return $this->cancel(
            subscription: $subscription,
            canceledAt: $reconciledAt,
            reason: 'trial_expired_without_payment',
            metadata: [
                'trial_ended_at' => $subscription->trial_ends_at->toIso8601String(),
            ],
        );
    }

    public function reconcileExpiredGracePeriod(Subscription $subscription, CarbonImmutable $reconciledAt): Subscription
    {
        if (
            $subscription->status !== SubscriptionStatus::PastDue
            || $subscription->grace_period_ends_at === null
            || $subscription->grace_period_ends_at->isFuture()
        ) {
            return $subscription;
        }

        return $this->cancel(
            subscription: $subscription,
            canceledAt: $reconciledAt,
            reason: 'grace_period_expired',
            metadata: [
                'grace_period_ended_at' => $subscription->grace_period_ends_at->toIso8601String(),
            ],
        );
    }

    private function emit(SubscriptionLifecycleEvent $event): void
    {
        OutboxMessage::query()->create([
            'aggregate_type' => 'subscription',
            'aggregate_id' => $event->subscription->id,
            'event_name' => $event->eventName(),
            'payload' => $event->payload(),
            'occurred_at' => $event->occurredAt,
        ]);

        DB::afterCommit(static fn () => event($event));
    }
}
