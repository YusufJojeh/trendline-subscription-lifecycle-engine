<?php

namespace App\Modules\Lifecycle\Events;

use App\Modules\Subscriptions\Models\Subscription;
use App\Shared\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class SubscriptionLifecycleEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public ?SubscriptionStatus $fromStatus,
        public SubscriptionStatus $toStatus,
        public string $reason,
        public CarbonImmutable $occurredAt,
        public array $metadata = [],
    ) {
    }

    public function eventName(): string
    {
        return class_basename(static::class);
    }

    public function payload(): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'user_id' => $this->subscription->user_id,
            'plan_id' => $this->subscription->plan_id,
            'plan_price_id' => $this->subscription->plan_price_id,
            'from_status' => $this->fromStatus?->value,
            'to_status' => $this->toStatus->value,
            'reason' => $this->reason,
            'occurred_at' => $this->occurredAt->toIso8601String(),
            'metadata' => $this->metadata,
        ];
    }
}
