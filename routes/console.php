<?php

use App\Modules\Lifecycle\Actions\ReconcileSubscriptionsAction;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('subscriptions:reconcile', function (ReconcileSubscriptionsAction $action) {
    $result = $action->execute();

    $this->info(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
})->purpose('Reconcile expired trials and grace periods');

Schedule::command('subscriptions:reconcile')->daily();
