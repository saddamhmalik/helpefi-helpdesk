<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Platform\Models\PlatformNotice;
use Illuminate\Database\Eloquent\Collection;

class PlatformNoticeRepository
{
    public function all(): Collection
    {
        return PlatformNotice::query()
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->get();
    }

    public function find(int $id): PlatformNotice
    {
        return PlatformNotice::query()->findOrFail($id);
    }

    public function create(array $data): PlatformNotice
    {
        return PlatformNotice::query()->create($data);
    }

    public function update(PlatformNotice $notice, array $data): PlatformNotice
    {
        $notice->update($data);

        return $notice->fresh();
    }

    public function delete(PlatformNotice $notice): void
    {
        $notice->delete();
    }

    public function activeForTenant(string $tenantId): Collection
    {
        $now = now();

        return PlatformNotice::query()
            ->where('status', PlatformNotice::STATUS_PUBLISHED)
            ->where('is_active', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function ($query) use ($tenantId) {
                $query
                    ->where('target_scope', PlatformNotice::TARGET_ALL)
                    ->orWhere(function ($selected) use ($tenantId) {
                        $selected
                            ->where('target_scope', PlatformNotice::TARGET_SELECTED)
                            ->whereJsonContains('tenant_ids', $tenantId);
                    });
            })
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END")
            ->orderByDesc('published_at')
            ->get();
    }
}
