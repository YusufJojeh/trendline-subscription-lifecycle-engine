<?php

namespace App\Modules\Subscriptions\DTOs;

readonly class StartSubscriptionData
{
    public function __construct(
        public int $userId,
        public int $planPriceId,
    ) {
    }

    public static function fromArray(array $attributes): self
    {
        return new self(
            userId: (int) $attributes['user_id'],
            planPriceId: (int) $attributes['plan_price_id'],
        );
    }
}
