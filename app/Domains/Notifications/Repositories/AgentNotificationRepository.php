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

    public function paginate(User $user, int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $query = $user->notifications()->latest();

        if (! empty($filters['unread'])) {
            $query->whereNull('read_at');
        }

        if (! empty($filters['type'])) {
            $query->where('data->type', $filters['type']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function deleteRead(User $user): int
    {
        return $user->notifications()->whereNotNull('read_at')->delete();
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
