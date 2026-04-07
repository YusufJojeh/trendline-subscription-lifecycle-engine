<?php

namespace App\Modules\Subscriptions\Http\Resources;

use App\Modules\Plans\Http\Resources\PlanPriceResource;
use App\Modules\Plans\Http\Resources\PlanResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'plan_id' => $this->plan_id,
            'plan_price_id' => $this->plan_price_id,
            'status' => $this->status->value,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'current_period_starts_at' => $this->current_period_starts_at?->toIso8601String(),
            'current_period_ends_at' => $this->current_period_ends_at?->toIso8601String(),
            'grace_period_ends_at' => $this->grace_period_ends_at?->toIso8601String(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'plan' => $this->whenLoaded('plan', fn () => new PlanResource($this->plan)),
            'plan_price' => $this->whenLoaded('planPrice', fn () => new PlanPriceResource($this->planPrice)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
