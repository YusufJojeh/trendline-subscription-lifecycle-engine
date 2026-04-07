<?php

namespace App\Modules\Plans\Http\Requests;

use App\Shared\Enums\BillingCycle;
use App\Shared\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $plan = $this->route('plan');

        return [
            'billing_cycle' => [
                'required',
                Rule::enum(BillingCycle::class),
                Rule::unique('plan_prices')->where(fn ($query) => $query
                    ->where('plan_id', $plan->id)
                    ->where('currency', $this->input('currency'))),
            ],
            'currency' => ['required', Rule::enum(Currency::class)],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
