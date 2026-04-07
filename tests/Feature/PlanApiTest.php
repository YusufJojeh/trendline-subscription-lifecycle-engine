<?php

namespace Tests\Feature;

use App\Modules\Plans\Models\Plan;
use App\Shared\Enums\BillingCycle;
use App\Shared\Enums\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_plan(): void
    {
        $response = $this->postJson('/api/v1/plans', [
            'name' => 'Starter',
            'code' => 'starter',
            'description' => 'Starter tier',
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.code', 'starter');

        $this->assertDatabaseHas('plans', [
            'code' => 'starter',
            'trial_days' => 14,
        ]);
    }

    public function test_it_creates_multi_currency_prices_for_a_plan(): void
    {
        $plan = Plan::factory()->create();

        $this->postJson("/api/v1/plans/{$plan->id}/prices", [
            'billing_cycle' => BillingCycle::Monthly->value,
            'currency' => Currency::AED->value,
            'amount_minor' => 9900,
        ])->assertCreated();

        $this->postJson("/api/v1/plans/{$plan->id}/prices", [
            'billing_cycle' => BillingCycle::Yearly->value,
            'currency' => Currency::USD->value,
            'amount_minor' => 19900,
        ])->assertCreated();

        $this->getJson("/api/v1/plans/{$plan->id}/prices")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('plan_prices', [
            'plan_id' => $plan->id,
            'billing_cycle' => BillingCycle::Monthly->value,
            'currency' => Currency::AED->value,
        ]);

        $this->assertDatabaseHas('plan_prices', [
            'plan_id' => $plan->id,
            'billing_cycle' => BillingCycle::Yearly->value,
            'currency' => Currency::USD->value,
        ]);
    }

    public function test_it_prevents_duplicate_pricing_for_same_cycle_and_currency(): void
    {
        $plan = Plan::factory()->create();

        $payload = [
            'billing_cycle' => BillingCycle::Monthly->value,
            'currency' => Currency::AED->value,
            'amount_minor' => 9900,
        ];

        $this->postJson("/api/v1/plans/{$plan->id}/prices", $payload)->assertCreated();

        $this->postJson("/api/v1/plans/{$plan->id}/prices", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['billing_cycle']);
    }

    public function test_it_rejects_invalid_plan_payloads(): void
    {
        $this->postJson('/api/v1/plans', [
            'name' => '',
            'trial_days' => -1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code', 'trial_days']);
    }
}
