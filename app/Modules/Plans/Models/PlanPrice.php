<?php

namespace App\Modules\Plans\Models;

use App\Modules\Subscriptions\Models\Subscription;
use App\Shared\Enums\BillingCycle;
use App\Shared\Enums\Currency;
use Database\Factories\PlanPriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'billing_cycle',
        'currency',
        'amount_minor',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'billing_cycle' => BillingCycle::class,
            'currency' => Currency::class,
            'amount_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    protected static function newFactory(): PlanPriceFactory
    {
        return PlanPriceFactory::new();
    }
}
