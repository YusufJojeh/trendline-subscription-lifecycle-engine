<?php

namespace Database\Factories;

use App\Modules\Plans\Models\Plan;
use App\Modules\Plans\Models\PlanPrice;
use App\Shared\Enums\BillingCycle;
use App\Shared\Enums\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanPrice>
 */
class PlanPriceFactory extends Factory
{
    protected $model = PlanPrice::class;

    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'billing_cycle' => BillingCycle::Monthly,
            'currency' => Currency::AED,
            'amount_minor' => 9900,
            'is_active' => true,
        ];
    }
}
