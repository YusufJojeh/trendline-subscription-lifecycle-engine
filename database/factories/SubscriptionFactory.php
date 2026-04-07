<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Plans\Models\Plan;
use App\Modules\Plans\Models\PlanPrice;
use App\Modules\Subscriptions\Models\Subscription;
use App\Shared\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $startsAt = Carbon::now()->subDay();
        $trialEndsAt = $startsAt->copy()->addWeek();
        $plan = Plan::factory();

        return [
            'user_id' => User::factory(),
            'plan_id' => $plan,
            'plan_price_id' => PlanPrice::factory()->for($plan),
            'status' => SubscriptionStatus::Trialing,
            'starts_at' => $startsAt,
            'trial_ends_at' => $trialEndsAt,
            'current_period_starts_at' => null,
            'current_period_ends_at' => null,
            'grace_period_ends_at' => null,
            'canceled_at' => null,
            'ended_at' => null,
        ];
    }
}
