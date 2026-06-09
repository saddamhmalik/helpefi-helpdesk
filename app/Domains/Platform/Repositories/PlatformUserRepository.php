<?php

namespace App\Domains\Platform\Repositories;

use App\Models\PlatformUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PlatformUserRepository
{
    public function find(int $id): PlatformUser
    {
        return PlatformUser::query()
            ->with('roles:id,name')
            ->findOrFail($id);
    }

    public function findByEmail(string $email): ?PlatformUser
    {
        return PlatformUser::query()->where('email', $email)->first();
    }

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return PlatformUser::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function active(): Collection
    {
        return PlatformUser::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): PlatformUser
    {
        return PlatformUser::query()->create($data);
    }

    public function update(PlatformUser $user, array $data): PlatformUser
    {
        $user->update($data);

        return $user->fresh(['roles']);
    }

    public function delete(PlatformUser $user): void
    {
        $user->roles()->detach();
        $user->delete();
    }
}
