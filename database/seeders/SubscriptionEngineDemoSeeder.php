<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Plans\Models\Plan;
use App\Modules\Plans\Models\PlanPrice;
use App\Modules\Subscriptions\Models\Subscription;
use App\Shared\Enums\BillingCycle;
use App\Shared\Enums\Currency;
use App\Shared\Enums\PaymentAttemptStatus;
use App\Shared\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SubscriptionEngineDemoSeeder extends Seeder
{
    public function run(): void
    {
        $anchor = CarbonImmutable::parse('2026-04-07 10:00:00');

        $reviewer = User::query()->updateOrCreate(
            ['email' => 'reviewer@example.com'],
            [
                'name' => 'Reviewer',
                'password' => Hash::make('password'),
                'email_verified_at' => $anchor,
            ]
        );

        $pastDueUser = User::query()->updateOrCreate(
            ['email' => 'pastdue@example.com'],
            [
                'name' => 'Grace scenario',
                'password' => Hash::make('password'),
                'email_verified_at' => $anchor,
            ]
        );

        $proPlan = Plan::query()->updateOrCreate(
            ['code' => 'algo-pro'],
            [
                'name' => 'Algo Pro',
                'description' => 'Pro tier with a 7-day trial.',
                'trial_days' => 7,
                'is_active' => true,
            ]
        );

        $corePlan = Plan::query()->updateOrCreate(
            ['code' => 'algo-core'],
            [
                'name' => 'Algo Core',
                'description' => 'Entry tier; no trial, first payment activates.',
                'trial_days' => 0,
                'is_active' => true,
            ]
        );

        $proMonthlyAed = PlanPrice::query()->updateOrCreate(
            [
                'plan_id' => $proPlan->id,
                'billing_cycle' => BillingCycle::Monthly->value,
                'currency' => Currency::AED->value,
            ],
            [
                'amount_minor' => 9900,
                'is_active' => true,
            ]
        );

        PlanPrice::query()->updateOrCreate(
            [
                'plan_id' => $proPlan->id,
                'billing_cycle' => BillingCycle::Yearly->value,
                'currency' => Currency::USD->value,
            ],
            [
                'amount_minor' => 99900,
                'is_active' => true,
            ]
        );

        PlanPrice::query()->updateOrCreate(
            [
                'plan_id' => $proPlan->id,
                'billing_cycle' => BillingCycle::Monthly->value,
                'currency' => Currency::EGP->value,
            ],
            [
                'amount_minor' => 129900,
                'is_active' => true,
            ]
        );

        PlanPrice::query()->updateOrCreate(
            [
                'plan_id' => $corePlan->id,
                'billing_cycle' => BillingCycle::Monthly->value,
                'currency' => Currency::AED->value,
            ],
            [
                'amount_minor' => 4900,
                'is_active' => true,
            ]
        );

        Subscription::query()->updateOrCreate(
            [
                'user_id' => $reviewer->id,
                'plan_price_id' => $proMonthlyAed->id,
                'starts_at' => $anchor->subDay(),
            ],
            [
                'plan_id' => $proPlan->id,
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => $anchor->addDays(6),
                'current_period_starts_at' => null,
                'current_period_ends_at' => null,
                'grace_period_ends_at' => null,
                'canceled_at' => null,
                'ended_at' => null,
            ]
        );

        $activeSubscription = Subscription::query()->updateOrCreate(
            [
                'user_id' => $reviewer->id,
                'plan_price_id' => $proMonthlyAed->id,
                'starts_at' => $anchor->subDays(20),
            ],
            [
                'plan_id' => $proPlan->id,
                'status' => SubscriptionStatus::Active,
                'trial_ends_at' => $anchor->subDays(13),
                'current_period_starts_at' => $anchor->subDays(20),
                'current_period_ends_at' => $anchor->addDays(10),
                'grace_period_ends_at' => null,
                'canceled_at' => null,
                'ended_at' => null,
            ]
        );

        $pastDueSubscription = Subscription::query()->updateOrCreate(
            [
                'user_id' => $pastDueUser->id,
                'plan_price_id' => $proMonthlyAed->id,
                'starts_at' => $anchor->subDays(35),
            ],
            [
                'plan_id' => $proPlan->id,
                'status' => SubscriptionStatus::PastDue,
                'trial_ends_at' => $anchor->subDays(28),
                'current_period_starts_at' => $anchor->subDays(35),
                'current_period_ends_at' => $anchor->subDays(5),
                'grace_period_ends_at' => $anchor->addDays(2),
                'canceled_at' => null,
                'ended_at' => null,
            ]
        );

        PaymentAttempt::query()->updateOrCreate(
            ['idempotency_key' => 'seed-active-payment-001'],
            [
                'subscription_id' => $activeSubscription->id,
                'amount_minor' => 9900,
                'currency' => Currency::AED->value,
                'status' => PaymentAttemptStatus::Successful,
                'provider_reference' => 'seed-provider-active-001',
                'attempted_at' => $anchor->subDays(20),
                'failure_reason' => null,
                'metadata' => ['source' => 'demo-seed'],
            ]
        );

        PaymentAttempt::query()->updateOrCreate(
            ['idempotency_key' => 'seed-failed-payment-001'],
            [
                'subscription_id' => $pastDueSubscription->id,
                'amount_minor' => 9900,
                'currency' => Currency::AED->value,
                'status' => PaymentAttemptStatus::Failed,
                'provider_reference' => 'seed-provider-failed-001',
                'attempted_at' => $anchor,
                'failure_reason' => 'Simulated failed renewal',
                'metadata' => ['source' => 'demo-seed'],
            ]
        );
    }
}
