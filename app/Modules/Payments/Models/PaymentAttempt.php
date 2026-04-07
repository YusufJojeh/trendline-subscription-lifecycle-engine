<?php

namespace App\Modules\Payments\Models;

use App\Modules\Subscriptions\Models\Subscription;
use App\Shared\Enums\Currency;
use App\Shared\Enums\PaymentAttemptStatus;
use Database\Factories\PaymentAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'amount_minor',
        'currency',
        'status',
        'idempotency_key',
        'provider_reference',
        'attempted_at',
        'failure_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'currency' => Currency::class,
            'status' => PaymentAttemptStatus::class,
            'attempted_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    protected static function newFactory(): PaymentAttemptFactory
    {
        return PaymentAttemptFactory::new();
    }
}
