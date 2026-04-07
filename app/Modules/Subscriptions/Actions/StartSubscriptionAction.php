<?php

namespace App\Modules\Subscriptions\Actions;

use App\Models\User;
use App\Modules\Lifecycle\Services\SubscriptionLifecycleManager;
use App\Modules\Plans\Models\PlanPrice;
use App\Modules\Subscriptions\DTOs\StartSubscriptionData;
use App\Modules\Subscriptions\Models\Subscription;
use App\Shared\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartSubscriptionAction
{
    public function __construct(
        private readonly SubscriptionLifecycleManager $lifecycleManager,
    ) {
    }

    public function execute(StartSubscriptionData $data): Subscription
    {
        return DB::transaction(function () use ($data): Subscription {
            $user = User::query()->findOrFail($data->userId);

            $planPrice = PlanPrice::query()
                ->with('plan')
                ->findOrFail($data->planPriceId);

            if (! $planPrice->is_active || ! $planPrice->plan->is_active) {
                throw ValidationException::withMessages([
                    'plan_price_id' => ['Subscriptions may only start on active plans and active prices.'],
                ]);
            }

            $subscription = Subscription::query()->create([
                'user_id' => $user->id,
                'plan_id' => $planPrice->plan_id,
                'plan_price_id' => $planPrice->id,
                'status' => SubscriptionStatus::Trialing,
            ]);

            $this->lifecycleManager->start(
                subscription: $subscription->load('plan', 'planPrice'),
                startedAt: CarbonImmutable::now(),
            );

            return $subscription->fresh(['user', 'plan', 'planPrice']);
        });
    }
}
