<?php

namespace App\Domains\Tickets\Repositories;

use App\Domains\Tickets\Models\TicketView;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TicketViewRepository
{
    public function forUser(int $userId): Collection
    {
        $teamIds = $this->userTeamIds($userId);

        return TicketView::query()
            ->with([
                'user:id,name',
                'team:id,name',
            ])
            ->where(function ($query) use ($userId, $teamIds) {
                $query->where('user_id', $userId);

                if ($teamIds !== []) {
                    $query->orWhere(function ($shared) use ($teamIds) {
                        $shared->where('visibility', TicketView::VISIBILITY_TEAM)
                            ->whereIn('team_id', $teamIds);
                    });
                }
            })
            ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$userId])
            ->orderBy('name')
            ->get();
    }

    public function findAccessible(int $id, int $userId): TicketView
    {
        $teamIds = $this->userTeamIds($userId);

        return TicketView::query()
            ->where(function ($query) use ($userId, $teamIds, $id) {
                $query->where('id', $id)
                    ->where(function ($access) use ($userId, $teamIds) {
                        $access->where('user_id', $userId);

                        if ($teamIds !== []) {
                            $access->orWhere(function ($shared) use ($teamIds) {
                                $shared->where('visibility', TicketView::VISIBILITY_TEAM)
                                    ->whereIn('team_id', $teamIds);
                            });
                        }
                    });
            })
            ->firstOrFail();
    }

    public function create(
        int $userId,
        string $name,
        array $filters,
        bool $isDefault = false,
        string $visibility = TicketView::VISIBILITY_PRIVATE,
        ?int $teamId = null,
    ): TicketView {
        if ($isDefault) {
            TicketView::query()
                ->where('user_id', $userId)
                ->where('visibility', TicketView::VISIBILITY_PRIVATE)
                ->update(['is_default' => false]);
        }

        return TicketView::query()->create([
            'user_id' => $userId,
            'name' => $name,
            'visibility' => $visibility,
            'team_id' => $teamId,
            'filters' => $filters,
            'is_default' => $isDefault && $visibility === TicketView::VISIBILITY_PRIVATE,
        ]);
    }

    public function delete(TicketView $view): void
    {
        $view->delete();
    }

    private function userTeamIds(int $userId): array
    {
        return DB::table('team_user')
            ->where('user_id', $userId)
            ->pluck('team_id')
            ->all();
    }
}
