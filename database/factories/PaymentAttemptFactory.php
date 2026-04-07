<?php

namespace Database\Factories;

use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Subscriptions\Models\Subscription;
use App\Shared\Enums\Currency;
use App\Shared\Enums\PaymentAttemptStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentAttempt>
 */
class PaymentAttemptFactory extends Factory
{
    protected $model = PaymentAttempt::class;

    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'amount_minor' => 9900,
            'currency' => Currency::AED,
            'status' => PaymentAttemptStatus::Successful,
            'idempotency_key' => (string) Str::uuid(),
            'provider_reference' => fake()->optional()->uuid(),
            'attempted_at' => Carbon::now(),
            'failure_reason' => null,
            'metadata' => ['source' => 'factory'],
        ];
    }
}
