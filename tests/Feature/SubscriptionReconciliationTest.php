<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Plans\Models\Plan;
use App\Modules\Plans\Models\PlanPrice;
use App\Modules\Subscriptions\Models\Subscription;
use App\Shared\Enums\BillingCycle;
use App\Shared\Enums\Currency;
use App\Shared\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_cancels_expired_trials_during_reconciliation_and_is_idempotent(): void
    {
        CarbonImmutable::setTestNow('2026-02-10 10:00:00');

        $subscription = $this->makeSubscription([
            'status' => SubscriptionStatus::Trialing,
            'starts_at' => '2026-02-01 10:00:00',
            'trial_ends_at' => '2026-02-05 10:00:00',
        ]);

        $this->artisan('subscriptions:reconcile')
            ->expectsOutputToContain('"expired_trials_canceled": 1')
            ->assertExitCode(0);

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Canceled, $subscription->status);
        $this->assertDatabaseHas('subscription_status_histories', [
            'subscription_id' => $subscription->id,
            'to_status' => SubscriptionStatus::Canceled->value,
            'reason' => 'trial_expired_without_payment',
        ]);
        $this->assertDatabaseCount('outbox_messages', 2);

        $this->artisan('subscriptions:reconcile')
            ->expectsOutputToContain('"expired_trials_canceled": 0')
            ->assertExitCode(0);

        $this->assertDatabaseCount('subscription_status_histories', 1);
        $this->assertDatabaseCount('outbox_messages', 2);
    }

    public function test_it_cancels_expired_grace_periods_during_reconciliation_and_is_idempotent(): void
    {
        CarbonImmutable::setTestNow('2026-02-10 10:00:00');

        $subscription = $this->makeSubscription([
            'status' => SubscriptionStatus::PastDue,
            'starts_at' => '2026-01-01 10:00:00',
            'current_period_starts_at' => '2026-01-01 10:00:00',
            'current_period_ends_at' => '2026-02-01 10:00:00',
            'grace_period_ends_at' => '2026-02-04 10:00:00',
        ]);

        $this->artisan('subscriptions:reconcile')
            ->expectsOutputToContain('"expired_grace_periods_canceled": 1')
            ->assertExitCode(0);

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Canceled, $subscription->status);

        $this->getJson("/api/v1/subscriptions/{$subscription->id}/access")
            ->assertOk()
            ->assertJsonPath('data.granted', false);
        $this->assertDatabaseCount('outbox_messages', 1);

        $this->artisan('subscriptions:reconcile')
            ->expectsOutputToContain('"expired_grace_periods_canceled": 0')
            ->assertExitCode(0);

        $this->assertDatabaseCount('outbox_messages', 1);
    }

    private function makeSubscription(array $attributes): Subscription
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['trial_days' => 7]);
        $price = PlanPrice::factory()->for($plan)->create([
            'billing_cycle' => BillingCycle::Monthly,
            'currency' => Currency::AED,
            'amount_minor' => 9900,
        ]);

        return Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_price_id' => $price->id,
            ...$attributes,
        ]);
    }
}
