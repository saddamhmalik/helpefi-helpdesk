<?php

namespace App\Domains\Billing\Repositories;

use App\Domains\Auth\Models\Invitation;
use App\Domains\Auth\Repositories\RoleRepository;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Tickets\Models\Ticket;
use App\Models\User;

class UsageRepository
{
    public function __construct(private RoleRepository $roles)
    {
    }

    public function agentCount(): int
    {
        return User::query()
            ->where(function ($query) {
                $query->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['admin', 'agent']))
                    ->orWhereHas('roles.permissions', fn ($permissions) => $permissions->where('name', 'access.agent'));
            })
            ->distinct()
            ->count('users.id');
    }

    public function pendingInviteCount(): int
    {
        return Invitation::query()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->whereIn('role', $this->roles->agentSeatRoleNames())
            ->count();
    }

    public function ticketsCreatedThisMonth(): int
    {
        return Ticket::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }
}
