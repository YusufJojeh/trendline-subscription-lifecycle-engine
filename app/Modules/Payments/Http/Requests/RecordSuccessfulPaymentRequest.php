<?php

namespace App\Modules\Payments\Http\Requests;

use App\Shared\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordSuccessfulPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subscription_id' => ['required', 'integer', 'exists:subscriptions,id'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'provider_reference' => ['nullable', 'string', 'max:255'],
            'attempted_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
