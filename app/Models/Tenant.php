<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $casts = [
        'is_blocked' => 'boolean',
        'byo_allowed' => 'boolean',
        'custom_domain_redirect' => 'boolean',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'is_blocked',
            'byo_allowed',
            'custom_domain_redirect',
            'razorpay_customer_id',
            'pm_type',
            'pm_last_four',
            'created_at',
            'updated_at',
        ];
    }

    public function subscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Domains\Billing\Models\Subscription::class, 'tenant_id', 'id');
    }

    public function infrastructure(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Domains\Tenancy\Models\TenantInfrastructure::class, 'tenant_id', 'id');
    }
}
