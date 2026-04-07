<?php

namespace Database\Factories;

use App\Modules\Plans\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'code' => Str::upper(fake()->unique()->lexify('plan_????')),
            'description' => fake()->sentence(),
            'trial_days' => 7,
            'is_active' => true,
        ];
    }
}
