<?php

namespace App\Modules\Subscriptions\Models;

use App\Models\User;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Plans\Models\Plan;
use App\Modules\Plans\Models\PlanPrice;
use App\Shared\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'plan_price_id',
        'status',
        'starts_at',
        'trial_ends_at',
        'current_period_starts_at',
        'current_period_ends_at',
        'grace_period_ends_at',
        'canceled_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'immutable_datetime',
            'trial_ends_at' => 'immutable_datetime',
            'current_period_starts_at' => 'immutable_datetime',
            'current_period_ends_at' => 'immutable_datetime',
            'grace_period_ends_at' => 'immutable_datetime',
            'canceled_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(\App\Modules\Audit\Models\SubscriptionStatusHistory::class);
    }

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }
}
