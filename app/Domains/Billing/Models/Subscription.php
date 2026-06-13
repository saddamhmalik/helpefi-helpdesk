<?php

namespace App\Domains\Billing\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $connection = 'central';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_TRIAL = 'trial';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'plan',
        'status',
        'trial_ends_at',
        'renews_at',
        'cancelled_at',
        'access_ends_at',
        'razorpay_subscription_id',
        'razorpay_plan_id',
        'billing_interval',
        'currency',
        'custom_amount',
        'active_addons',
        'razorpay_addon_items',
    ];

    protected function casts(): array
    {
        return [
            'custom_amount' => 'integer',
            'trial_ends_at' => 'datetime',
            'renews_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'access_ends_at' => 'datetime',
            'active_addons' => 'array',
            'razorpay_addon_items' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isOnTrial(): bool
    {
        return $this->status === self::STATUS_TRIAL
            && ($this->trial_ends_at === null || $this->trial_ends_at->isFuture());
    }

    public function isTrialExpired(): bool
    {
        return $this->status === self::STATUS_TRIAL
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isPast();
    }

    public function isAccessible(): bool
    {
        return $this->isActive() || $this->isOnTrial() || $this->isInGracePeriod();
    }

    public function isInGracePeriod(): bool
    {
        return $this->access_ends_at !== null && $this->access_ends_at->isFuture();
    }

    public function isCancelledPendingGrace(): bool
    {
        return $this->status === self::STATUS_CANCELLED && $this->isInGracePeriod();
    }

    public function graceDaysRemaining(): ?int
    {
        if (! $this->isInGracePeriod()) {
            return null;
        }

        return max(0, (int) now()->diffInDays($this->access_ends_at, false));
    }

    public function trialDaysRemaining(): ?int
    {
        if (! $this->isOnTrial() || $this->trial_ends_at === null) {
            return null;
        }

        return max(0, (int) now()->diffInDays($this->trial_ends_at, false));
    }
}
