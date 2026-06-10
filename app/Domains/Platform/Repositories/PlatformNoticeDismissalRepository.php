<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Platform\Models\PlatformNoticeDismissal;
use App\Models\User;
use Illuminate\Support\Collection;

class PlatformNoticeDismissalRepository
{
    public function dismissedIds(User $user): array
    {
        return PlatformNoticeDismissal::query()
            ->where('user_id', $user->id)
            ->pluck('platform_notice_id')
            ->all();
    }

    public function dismiss(User $user, int $platformNoticeId): void
    {
        PlatformNoticeDismissal::query()->updateOrCreate(
            [
                'platform_notice_id' => $platformNoticeId,
                'user_id' => $user->id,
            ],
            ['dismissed_at' => now()],
        );
    }

    public function dismissedForUser(User $user, Collection|array $noticeIds): Collection
    {
        $ids = collect($noticeIds)->map(fn ($id) => (int) $id)->filter()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return PlatformNoticeDismissal::query()
            ->where('user_id', $user->id)
            ->whereIn('platform_notice_id', $ids)
            ->pluck('platform_notice_id');
    }
}
