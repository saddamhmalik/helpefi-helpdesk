<?php

namespace App\Domains\Billing\Repositories;

use App\Domains\Auth\Models\Invitation;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Tickets\Models\Ticket;
use App\Models\User;

class UsageRepository
{
    public function agentCount(): int
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'agent']))
            ->count();
    }

    public function pendingInviteCount(): int
    {
        return Invitation::query()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->whereIn('role', ['admin', 'agent'])
            ->count();
    }

    public function ticketsCreatedThisMonth(): int
    {
        return Ticket::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }
}
