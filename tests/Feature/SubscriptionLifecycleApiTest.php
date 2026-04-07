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

class SubscriptionLifecycleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_starts_a_trial_subscription_and_grants_access_during_trial(): void
    {
        CarbonImmutable::setTestNow('2026-01-01 10:00:00');

        [$user, $price] = $this->makeUserAndPrice(14);

        $response = $this->postJson('/api/v1/subscriptions', [
            'user_id' => $user->id,
            'plan_price_id' => $price->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', SubscriptionStatus::Trialing->value);

        $subscriptionId = $response->json('data.id');

        $this->getJson("/api/v1/subscriptions/{$subscriptionId}/access")
            ->assertOk()
            ->assertJsonPath('data.granted', true);

        $this->assertDatabaseHas('subscription_status_histories', [
            'subscription_id' => $subscriptionId,
            'to_status' => SubscriptionStatus::Trialing->value,
            'reason' => 'subscription_started',
        ]);
    }

    public function test_zero_day_trial_requires_immediate_payment_and_grants_no_access(): void
    {
        CarbonImmutable::setTestNow('2026-01-01 10:00:00');

        [$user, $price] = $this->makeUserAndPrice(0);

        $response = $this->postJson('/api/v1/subscriptions', [
            'user_id' => $user->id,
            'plan_price_id' => $price->id,
        ])->assertCreated();

        $subscriptionId = $response->json('data.id');

        $response->assertJsonPath('data.status', SubscriptionStatus::Trialing->value)
            ->assertJsonPath('data.trial_ends_at', '2026-01-01T10:00:00+00:00');

        $this->getJson("/api/v1/subscriptions/{$subscriptionId}/access")
            ->assertOk()
            ->assertJsonPath('data.granted', false);
    }

    public function test_successful_payment_activates_a_subscription_and_records_history(): void
    {
        CarbonImmutable::setTestNow('2026-01-01 10:00:00');

        [$subscription, $price] = $this->startSubscription();

        $response = $this->postJson('/api/v1/payments/success', [
            'subscription_id' => $subscription->id,
            'amount_minor' => $price->amount_minor,
            'currency' => $price->currency->value,
            'idempotency_key' => 'pay-success-1',
            'provider_reference' => 'provider-success-1',
            'attempted_at' => '2026-01-01T10:00:00Z',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'successful');

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame('2026-02-01T10:00:00+00:00', $subscription->current_period_ends_at?->toIso8601String());

        $this->assertDatabaseHas('subscription_status_histories', [
            'subscription_id' => $subscription->id,
            'to_status' => SubscriptionStatus::Active->value,
            'reason' => 'payment_succeeded',
        ]);
    }

    public function test_failed_payment_moves_active_subscription_to_past_due_and_keeps_access_during_grace(): void
    {
        CarbonImmutable::setTestNow('2026-01-01 10:00:00');

        [$subscription, $price] = $this->startSubscription();

        $this->postJson('/api/v1/payments/success', [
            'subscription_id' => $subscription->id,
            'amount_minor' => $price->amount_minor,
            'currency' => $price->currency->value,
            'idempotency_key' => 'activate-before-fail',
            'attempted_at' => '2026-01-01T10:00:00Z',
        ])->assertOk();

        $this->postJson('/api/v1/payments/failure', [
            'subscription_id' => $subscription->id,
            'amount_minor' => $price->amount_minor,
            'currency' => $price->currency->value,
            'idempotency_key' => 'pay-fail-1',
            'attempted_at' => '2026-01-15T10:00:00Z',
            'failure_reason' => 'Card declined',
        ])->assertOk();

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::PastDue, $subscription->status);
        $this->assertSame('2026-01-18T10:00:00+00:00', $subscription->grace_period_ends_at?->toIso8601String());

        CarbonImmutable::setTestNow('2026-01-16 10:00:00');

        $this->getJson("/api/v1/subscriptions/{$subscription->id}/access")
            ->assertOk()
            ->assertJsonPath('data.granted', true);
    }

    public function test_successful_payment_during_grace_period_recovers_subscription_to_active(): void
    {
        CarbonImmutable::setTestNow('2026-01-01 10:00:00');

        [$subscription, $price] = $this->startSubscription();

        $this->postJson('/api/v1/payments/success', [
            'subscription_id' => $subscription->id,
            'amount_minor' => $price->amount_minor,
            'currency' => $price->currency->value,
            'idempotency_key' => 'activate-before-recovery',
            'attempted_at' => '2026-01-01T10:00:00Z',
        ])->assertOk();

        $this->postJson('/api/v1/payments/failure', [
            'subscription_id' => $subscription->id,
            'amount_minor' => $price->amount_minor,
            'currency' => $price->currency->value,
            'idempotency_key' => 'pay-fail-recovery',
            'attempted_at' => '2026-01-15T10:00:00Z',
            'failure_reason' => 'Card declined',
        ])->assertOk();

        $this->postJson('/api/v1/payments/success', [
            'subscription_id' => $subscription->id,
            'amount_minor' => $price->amount_minor,
            'currency' => $price->currency->value,
            'idempotency_key' => 'pay-success-recovery',
            'attempted_at' => '2026-01-16T12:00:00Z',
        ])->assertOk();

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNull($subscription->grace_period_ends_at);
    }

    public function test_payment_recording_is_idempotent(): void
    {
        CarbonImmutable::setTestNow('2026-01-01 10:00:00');

        [$subscription, $price] = $this->startSubscription();

        $payload = [
            'subscription_id' => $subscription->id,
            'amount_minor' => $price->amount_minor,
            'currency' => $price->currency->value,
            'idempotency_key' => 'same-event-1',
            'provider_reference' => 'provider-same-event-1',
            'attempted_at' => '2026-01-01T10:00:00Z',
        ];

        $first = $this->postJson('/api/v1/payments/success', $payload)->assertOk();
        $second = $this->postJson('/api/v1/payments/success', $payload)->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('payment_attempts', 1);
    }

    public function test_duplicate_idempotency_key_with_different_payload_is_rejected(): void
    {
        CarbonImmutable::setTestNow('2026-01-01 10:00:00');

        [$subscription, $price] = $this->startSubscription();

        $this->postJson('/api/v1/payments/success', [
            'subscription_id' => $subscription->id,
            'amount_minor' => $price->amount_minor,
            'currency' => $price->currency->value,
            'idempotency_key' => 'dup-key-1',
            'provider_reference' => 'provider-dup-key-1',
            'attempted_at' => '2026-01-01T10:00:00Z',
        ])->assertOk();

        $this->postJson('/api/v1/payments/success', [
            'subscription_id' => $subscription->id,
            'amount_minor' => $price->amount_minor + 100,
            'currency' => $price->currency->value,
            'idempotency_key' => 'dup-key-1',
            'provider_reference' => 'provider-dup-key-2',
            'attempted_at' => '2026-01-01T10:05:00Z',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);

        $this->assertDatabaseCount('payment_attempts', 1);
    }

    public function test_payment_success_after_cancellation_does_not_silently_reactivate(): void
    {
        CarbonImmutable::setTestNow('2026-01-01 10:00:00');

        [$subscription, $price] = $this->startSubscription();

        $this->postJson("/api/v1/subscriptions/{$subscription->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::Canceled->value);

        $this->postJson('/api/v1/payments/success', [
            'subscription_id' => $subscription->id,
            'amount_minor' => $price->amount_minor,
            'currency' => $price->currency->value,
            'idempotency_key' => 'canceled-payment-1',
            'attempted_at' => '2026-01-01T10:15:00Z',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['subscription']);

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Canceled, $subscription->status);
        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_manual_cancellation_is_idempotent(): void
    {
        CarbonImmutable::setTestNow('2026-01-01 10:00:00');

        [$subscription] = $this->startSubscription();

        $this->postJson("/api/v1/subscriptions/{$subscription->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::Canceled->value);

        $this->postJson("/api/v1/subscriptions/{$subscription->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::Canceled->value);

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Canceled, $subscription->status);
        $this->assertDatabaseCount('subscription_status_histories', 2);
        $this->assertDatabaseCount('outbox_messages', 2);
    }

    public function test_access_is_revoked_at_the_exact_grace_period_boundary(): void
    {
        CarbonImmutable::setTestNow('2026-01-01 10:00:00');

        [$subscription, $price] = $this->startSubscription();

        $this->postJson('/api/v1/payments/success', [
            'subscription_id' => $subscription->id,
            'amount_minor' => $price->amount_minor,
            'currency' => $price->currency->value,
            'idempotency_key' => 'activate-for-boundary',
            'attempted_at' => '2026-01-01T10:00:00Z',
        ])->assertOk();

        $this->postJson('/api/v1/payments/failure', [
            'subscription_id' => $subscription->id,
            'amount_minor' => $price->amount_minor,
            'currency' => $price->currency->value,
            'idempotency_key' => 'fail-for-boundary',
            'attempted_at' => '2026-01-15T10:00:00Z',
            'failure_reason' => 'Card declined',
        ])->assertOk();

        CarbonImmutable::setTestNow('2026-01-18 09:59:59');
        $this->getJson("/api/v1/subscriptions/{$subscription->id}/access")
            ->assertOk()
            ->assertJsonPath('data.granted', true);

        CarbonImmutable::setTestNow('2026-01-18 10:00:00');
        $this->getJson("/api/v1/subscriptions/{$subscription->id}/access")
            ->assertOk()
            ->assertJsonPath('data.granted', false);
    }

    public function test_repeated_payment_failure_while_already_past_due_does_not_extend_grace_or_duplicate_transitions(): void
    {
        CarbonImmutable::setTestNow('2026-01-01 10:00:00');

        [$subscription, $price] = $this->startSubscription();

        $this->postJson('/api/v1/payments/success', [
            'subscription_id' => $subscription->id,
            'amount_minor' => $price->amount_minor,
            'currency' => $price->currency->value,
            'idempotency_key' => 'activate-before-repeat-failure',
            'attempted_at' => '2026-01-01T10:00:00Z',
        ])->assertOk();

        $this->postJson('/api/v1/payments/failure', [
            'subscription_id' => $subscription->id,
            'amount_minor' => $price->amount_minor,
            'currency' => $price->currency->value,
            'idempotency_key' => 'first-failure',
            'attempted_at' => '2026-01-15T10:00:00Z',
            'failure_reason' => 'Card declined',
        ])->assertOk();

        $subscription->refresh();
        $originalGracePeriodEnd = $subscription->grace_period_ends_at?->toIso8601String();

        $this->postJson('/api/v1/payments/failure', [
            'subscription_id' => $subscription->id,
            'amount_minor' => $price->amount_minor,
            'currency' => $price->currency->value,
            'idempotency_key' => 'second-failure',
            'attempted_at' => '2026-01-16T10:00:00Z',
            'failure_reason' => 'Still declined',
        ])->assertOk();

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::PastDue, $subscription->status);
        $this->assertSame($originalGracePeriodEnd, $subscription->grace_period_ends_at?->toIso8601String());
        $this->assertDatabaseCount('payment_attempts', 3);
        $this->assertDatabaseCount('subscription_status_histories', 3);
        $this->assertDatabaseCount('outbox_messages', 4);
    }

    private function startSubscription(): array
    {
        [$user, $price] = $this->makeUserAndPrice(7);

        $response = $this->postJson('/api/v1/subscriptions', [
            'user_id' => $user->id,
            'plan_price_id' => $price->id,
        ])->assertCreated();

        $subscription = Subscription::query()->findOrFail($response->json('data.id'));

        return [$subscription, $price];
    }

    private function makeUserAndPrice(int $trialDays): array
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create([
            'trial_days' => $trialDays,
        ]);
        $price = PlanPrice::factory()->for($plan)->create([
            'billing_cycle' => BillingCycle::Monthly,
            'currency' => Currency::AED,
            'amount_minor' => 9900,
        ]);

        return [$user, $price];
    }
}
