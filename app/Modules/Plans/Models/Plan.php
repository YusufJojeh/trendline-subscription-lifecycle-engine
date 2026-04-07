<?php

namespace App\Modules\Plans\Models;

use App\Modules\Subscriptions\Models\Subscription;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'trial_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'trial_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    protected static function newFactory(): PlanFactory
    {
        return PlanFactory::new();
    }
}
