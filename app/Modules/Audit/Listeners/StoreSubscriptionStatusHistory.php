<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\Models\SubscriptionStatusHistory;
use App\Modules\Lifecycle\Events\SubscriptionLifecycleEvent;

class StoreSubscriptionStatusHistory
{
    public function handle(SubscriptionLifecycleEvent $event): void
    {
        if ($event->fromStatus === $event->toStatus) {
            return;
        }

        SubscriptionStatusHistory::query()->create([
            'subscription_id' => $event->subscription->id,
            'from_status' => $event->fromStatus,
            'to_status' => $event->toStatus,
            'reason' => $event->reason,
            'metadata' => $event->metadata,
            'changed_at' => $event->occurredAt,
        ]);
    }
}
