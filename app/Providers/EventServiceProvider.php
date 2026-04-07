<?php

namespace App\Providers;

use App\Modules\Audit\Listeners\LogSubscriptionLifecycleEvent;
use App\Modules\Audit\Listeners\StoreSubscriptionStatusHistory;
use App\Modules\Lifecycle\Events\SubscriptionActivated;
use App\Modules\Lifecycle\Events\SubscriptionCanceled;
use App\Modules\Lifecycle\Events\SubscriptionMovedToPastDue;
use App\Modules\Lifecycle\Events\SubscriptionPaymentFailed;
use App\Modules\Lifecycle\Events\SubscriptionStarted;
use App\Modules\Lifecycle\Events\TrialExpired;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        SubscriptionStarted::class => [
            StoreSubscriptionStatusHistory::class,
            LogSubscriptionLifecycleEvent::class,
        ],
        SubscriptionActivated::class => [
            StoreSubscriptionStatusHistory::class,
            LogSubscriptionLifecycleEvent::class,
        ],
        SubscriptionPaymentFailed::class => [
            StoreSubscriptionStatusHistory::class,
            LogSubscriptionLifecycleEvent::class,
        ],
        SubscriptionMovedToPastDue::class => [
            StoreSubscriptionStatusHistory::class,
            LogSubscriptionLifecycleEvent::class,
        ],
        TrialExpired::class => [
            StoreSubscriptionStatusHistory::class,
            LogSubscriptionLifecycleEvent::class,
        ],
        SubscriptionCanceled::class => [
            StoreSubscriptionStatusHistory::class,
            LogSubscriptionLifecycleEvent::class,
        ],
    ];
}
