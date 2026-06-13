<?php

namespace App\Domains\Tenancy\Repositories;

use App\Domains\Tenancy\Models\PendingRegistration;

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
}
