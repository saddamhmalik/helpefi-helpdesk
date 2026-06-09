<?php

namespace App\Domains\Notifications\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AgentNotificationRepository
{
    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function recent(User $user, int $limit = 8): Collection
    {
        return $user->notifications()->limit($limit)->get();
    }

    public function paginate(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $user->notifications()->paginate($perPage);
    }

    public function markRead(User $user, string $id): void
    {
        $user->notifications()->where('id', $id)->first()?->markAsRead();
    }

    public function markAllRead(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }
}
