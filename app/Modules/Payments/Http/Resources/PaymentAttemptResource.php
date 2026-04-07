<?php

namespace App\Modules\Payments\Http\Resources;

use App\Modules\Subscriptions\Http\Resources\SubscriptionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subscription_id' => $this->subscription_id,
            'amount_minor' => $this->amount_minor,
            'currency' => $this->currency->value,
            'status' => $this->status->value,
            'idempotency_key' => $this->idempotency_key,
            'provider_reference' => $this->provider_reference,
            'attempted_at' => $this->attempted_at?->toIso8601String(),
            'failure_reason' => $this->failure_reason,
            'metadata' => $this->metadata ?? [],
            'subscription' => $this->whenLoaded('subscription', fn () => new SubscriptionResource($this->subscription)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
