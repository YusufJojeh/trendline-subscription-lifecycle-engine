<?php

namespace App\Modules\Plans\Http\Requests;

use App\Modules\Plans\Models\PlanPrice;
use App\Shared\Enums\BillingCycle;
use App\Shared\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var PlanPrice $planPrice */
        $planPrice = $this->route('price');
        $plan = $this->route('plan');
        $billingCycle = $this->input('billing_cycle', $planPrice->billing_cycle->value);
        $currency = $this->input('currency', $planPrice->currency->value);

        return [
            'billing_cycle' => [
                'sometimes',
                Rule::enum(BillingCycle::class),
                Rule::unique('plan_prices')
                    ->ignore($planPrice->id)
                    ->where(fn ($query) => $query
                        ->where('plan_id', $plan->id)
                        ->where('billing_cycle', $billingCycle)
                        ->where('currency', $currency)),
            ],
            'currency' => ['sometimes', Rule::enum(Currency::class)],
            'amount_minor' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
