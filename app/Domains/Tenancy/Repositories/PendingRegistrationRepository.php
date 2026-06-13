<?php

namespace App\Domains\Tenancy\Repositories;

use App\Domains\Tenancy\Models\PendingRegistration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PendingRegistrationRepository
{
    public function create(array $data): PendingRegistration
    {
        return PendingRegistration::query()->create($data);
    }

    public function findByToken(string $token): ?PendingRegistration
    {
        return PendingRegistration::query()->where('token', $token)->first();
    }

    public function latestPendingForEmail(string $email): ?PendingRegistration
    {
        return PendingRegistration::query()
            ->where('admin_email', $email)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();
    }

    public function slugReserved(string $slug): bool
    {
        return PendingRegistration::query()
            ->where('slug', $slug)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function deleteUnverifiedForEmail(string $email): void
    {
        PendingRegistration::query()
            ->where('admin_email', $email)
            ->whereNull('verified_at')
            ->delete();
    }

    public function save(PendingRegistration $registration): PendingRegistration
    {
        $registration->save();

        return $registration;
    }

    public function delete(PendingRegistration $registration): void
    {
        $registration->delete();
    }

    public function findUnverified(int $id): ?PendingRegistration
    {
        return PendingRegistration::query()
            ->whereKey($id)
            ->whereNull('verified_at')
            ->first();
    }

    public function paginateUnverified(int $perPage = 20, ?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        return PendingRegistration::query()
            ->whereNull('verified_at')
            ->when(filled($search), function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('organization_name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('admin_email', 'like', "%{$search}%")
                        ->orWhere('admin_name', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('expires_at', '>', now()))
            ->when($status === 'expired', fn ($query) => $query->where('expires_at', '<=', now()))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function stats(): array
    {
        $base = PendingRegistration::query()->whereNull('verified_at');

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('expires_at', '>', now())->count(),
            'expired' => (clone $base)->where('expires_at', '<=', now())->count(),
        ];
    }

    public function deleteExpiredUnverified(): int
    {
        return PendingRegistration::query()
            ->whereNull('verified_at')
            ->where('expires_at', '<=', now())
            ->delete();
    }
}
