<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Tickets\Models\TicketView;
use App\Domains\Tickets\Repositories\TicketViewRepository;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class TicketViewService
{
    public function __construct(private TicketViewRepository $views)
    {
    }

    public function forUser(int $userId): Collection
    {
        return $this->views->forUser($userId);
    }

    public function findAccessible(int $id, int $userId): TicketView
    {
        return $this->views->findAccessible($id, $userId);
    }

    public function create(
        User $user,
        string $name,
        array $filters,
        bool $isDefault = false,
        string $visibility = TicketView::VISIBILITY_PRIVATE,
        ?int $teamId = null,
    ): TicketView {
        if ($visibility === TicketView::VISIBILITY_TEAM) {
            if (! $teamId) {
                throw ValidationException::withMessages([
                    'team_id' => 'Select a team to share this view with.',
                ]);
            }

            $this->assertTeamMembership($user, $teamId);
        }

        return $this->views->create(
            $user->id,
            $name,
            $filters,
            $isDefault,
            $visibility,
            $visibility === TicketView::VISIBILITY_TEAM ? $teamId : null,
        );
    }

    public function delete(int $id, int $userId): void
    {
        $view = $this->views->findAccessible($id, $userId);

        if (! $view->isOwnedBy($userId)) {
            throw ValidationException::withMessages([
                'view' => 'Only the creator can delete this saved view.',
            ]);
        }

        $this->views->delete($view);
    }

    private function assertTeamMembership(User $user, int $teamId): void
    {
        if (! $user->teams()->where('teams.id', $teamId)->exists()) {
            throw ValidationException::withMessages([
                'team_id' => 'You can only share views with teams you belong to.',
            ]);
        }
    }
}
