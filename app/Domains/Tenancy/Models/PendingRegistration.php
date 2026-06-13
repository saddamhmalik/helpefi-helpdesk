<?php

namespace App\Domains\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

class PendingRegistration extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'organization_name',
        'slug',
        'admin_name',
        'admin_email',
        'password',
        'token',
        'expires_at',
        'verified_at',
    ];

    protected $hidden = [
        'password',
        'token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
