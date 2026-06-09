<?php

namespace App\Domains\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'plan',
        'status',
        'renews_at',
    ];

    protected function casts(): array
    {
        return [
            'renews_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
