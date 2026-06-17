<?php

namespace App\Domains\Auth\Repositories;

use App\Domains\Auth\Models\Invitation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class InvitationRepository
{
    public function findByToken(string $token): Invitation
    {
        return Invitation::query()->where('token', $token)->firstOrFail();
    }

    public function findById(int $id): Invitation
    {
        return Invitation::query()->findOrFail($id);
    }

    public function pendingForEmail(string $email): ?Invitation
    {
        return Invitation::query()
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function create(array $data): Invitation
    {
        return Invitation::query()->create($data);
    }

    public function markAccepted(Invitation $invitation): Invitation
    {
        $invitation->update(['accepted_at' => now()]);

        return $invitation->fresh();
    }

    public function pending(): Collection
    {
        return Invitation::query()
            ->with('inviter:id,name')
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();
    }
}
