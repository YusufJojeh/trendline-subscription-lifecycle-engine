<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Lifecycle\Services\SubscriptionLifecycleManager;
use App\Modules\Payments\DTOs\RecordPaymentData;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Subscriptions\Models\Subscription;
use App\Shared\Enums\PaymentAttemptStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordPaymentAttemptAction
{
    public function __construct(
        private readonly SubscriptionLifecycleManager $lifecycleManager,
    ) {
    }

    public function recordSuccessful(RecordPaymentData $data): PaymentAttempt
    {
        return $this->record($data, PaymentAttemptStatus::Successful);
    }

    public function recordFailed(RecordPaymentData $data): PaymentAttempt
    {
        return $this->record($data, PaymentAttemptStatus::Failed);
    }

    private function record(RecordPaymentData $data, PaymentAttemptStatus $status): PaymentAttempt
    {
        return DB::transaction(function () use ($data, $status): PaymentAttempt {
            $existingByIdempotencyKey = PaymentAttempt::query()
                ->where('idempotency_key', $data->idempotencyKey)
                ->first();

            if ($existingByIdempotencyKey !== null) {
                $this->ensureIdempotencyPayloadMatches($existingByIdempotencyKey, $data, $status);

                return $existingByIdempotencyKey->fresh(['subscription.plan', 'subscription.planPrice']);
            }

            $existingByProviderReference = $data->providerReference === null
                ? null
                : PaymentAttempt::query()
                    ->where('provider_reference', $data->providerReference)
                    ->first();

            if ($existingByProviderReference !== null) {
                return $existingByProviderReference->fresh(['subscription.plan', 'subscription.planPrice']);
            }

            $subscription = Subscription::query()
                ->with('plan', 'planPrice')
                ->lockForUpdate()
                ->findOrFail($data->subscriptionId);

            if ($data->currency !== $subscription->planPrice->currency->value) {
                throw ValidationException::withMessages([
                    'currency' => ['Payment currency must match the selected subscription price currency.'],
                ]);
            }

            if ($data->amountMinor !== $subscription->planPrice->amount_minor) {
                throw ValidationException::withMessages([
                    'amount_minor' => ['Payment amount must match the selected subscription price amount.'],
                ]);
            }

            try {
                $paymentAttempt = PaymentAttempt::query()->create([
                    'subscription_id' => $subscription->id,
                    'amount_minor' => $data->amountMinor,
                    'currency' => $data->currency,
                    'status' => $status,
                    'idempotency_key' => $data->idempotencyKey,
                    'provider_reference' => $data->providerReference,
                    'attempted_at' => $data->attemptedAt,
                    'failure_reason' => $status === PaymentAttemptStatus::Failed ? $data->failureReason : null,
                    'metadata' => $data->metadata,
                ]);
            } catch (QueryException $exception) {
                if (! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                $existingPaymentAttempt = PaymentAttempt::query()
                    ->where('idempotency_key', $data->idempotencyKey)
                    ->when(
                        $data->providerReference !== null,
                        fn ($query) => $query->orWhere('provider_reference', $data->providerReference)
                    )
                    ->first();

                if ($existingPaymentAttempt === null) {
                    throw $exception;
                }

                if ($existingPaymentAttempt->idempotency_key === $data->idempotencyKey) {
                    $this->ensureIdempotencyPayloadMatches($existingPaymentAttempt, $data, $status);
                }

                return $existingPaymentAttempt->fresh(['subscription.plan', 'subscription.planPrice']);
            }

            $lifecycleMetadata = [
                'payment_attempt_id' => $paymentAttempt->id,
                'idempotency_key' => $paymentAttempt->idempotency_key,
                'provider_reference' => $paymentAttempt->provider_reference,
            ];

            if ($status === PaymentAttemptStatus::Successful) {
                $this->lifecycleManager->activate(
                    subscription: $subscription,
                    paidAt: $data->attemptedAt,
                    metadata: $lifecycleMetadata,
                );
            }

            if ($status === PaymentAttemptStatus::Failed) {
                $this->lifecycleManager->markPaymentFailed(
                    subscription: $subscription,
                    failedAt: $data->attemptedAt,
                    metadata: $lifecycleMetadata + [
                        'failure_reason' => $paymentAttempt->failure_reason,
                    ],
                );
            }

            return $paymentAttempt->fresh(['subscription.plan', 'subscription.planPrice']);
        });
    }

    private function ensureIdempotencyPayloadMatches(
        PaymentAttempt $existingPaymentAttempt,
        RecordPaymentData $data,
        PaymentAttemptStatus $status,
    ): void {
        $hasConflict = $existingPaymentAttempt->subscription_id !== $data->subscriptionId
            || $existingPaymentAttempt->amount_minor !== $data->amountMinor
            || $existingPaymentAttempt->currency->value !== $data->currency
            || $existingPaymentAttempt->status !== $status;

        if ($hasConflict) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['This idempotency key has already been used for a different payment attempt.'],
            ]);
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique constraint')
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'integrity constraint violation');
    }
}
