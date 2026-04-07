<?php

namespace App\Modules\Audit\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutboxMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'aggregate_type',
        'aggregate_id',
        'event_name',
        'payload',
        'occurred_at',
        'processed_at',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
            'attempts' => 'integer',
        ];
    }
}
