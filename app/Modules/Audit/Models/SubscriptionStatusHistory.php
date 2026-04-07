<?php

namespace App\Modules\Audit\Models;

use App\Modules\Subscriptions\Models\Subscription;
use App\Shared\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'from_status',
        'to_status',
        'reason',
        'metadata',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => SubscriptionStatus::class,
            'to_status' => SubscriptionStatus::class,
            'metadata' => 'array',
            'changed_at' => 'immutable_datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
