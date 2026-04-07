<?php

namespace App\Modules\Payments\DTOs;

use Carbon\CarbonImmutable;

readonly class RecordPaymentData
{
    public function __construct(
        public int $subscriptionId,
        public int $amountMinor,
        public string $currency,
        public string $idempotencyKey,
        public ?string $providerReference,
        public CarbonImmutable $attemptedAt,
        public ?string $failureReason,
        public array $metadata,
    ) {
    }

    public static function fromArray(array $attributes): self
    {
        return new self(
            subscriptionId: (int) $attributes['subscription_id'],
            amountMinor: (int) $attributes['amount_minor'],
            currency: (string) $attributes['currency'],
            idempotencyKey: (string) $attributes['idempotency_key'],
            providerReference: $attributes['provider_reference'] ?? null,
            attemptedAt: CarbonImmutable::parse($attributes['attempted_at'] ?? now()),
            failureReason: $attributes['failure_reason'] ?? null,
            metadata: $attributes['metadata'] ?? [],
        );
    }
}
