<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Lifecycle\Events\SubscriptionLifecycleEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogSubscriptionLifecycleEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(SubscriptionLifecycleEvent $event): void
    {
        Log::info('subscription.lifecycle_event', [
            'event_name' => $event->eventName(),
            ...$event->payload(),
        ]);
    }
}
