<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Platform\Models\PlatformBackup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PlatformBackupRepository
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return PlatformBackup::query()
            ->with(['tenant:id,name,slug', 'creator:id,name,email'])
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): PlatformBackup
    {
        return PlatformBackup::query()
            ->with(['tenant:id,name,slug', 'creator:id,name,email'])
            ->findOrFail($id);
    }

    public function create(array $data): PlatformBackup
    {
        return PlatformBackup::query()->create($data);
    }

    public function update(PlatformBackup $backup, array $data): PlatformBackup
    {
        $backup->update($data);

        return $backup->fresh(['tenant:id,name,slug', 'creator:id,name,email']);
    }

    public function delete(PlatformBackup $backup): void
    {
        $backup->delete();
    }

    public function expired(int $days): Collection
    {
        return PlatformBackup::query()
            ->where('created_at', '<', now()->subDays($days))
            ->get();
    }
}
