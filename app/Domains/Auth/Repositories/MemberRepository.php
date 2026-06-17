<?php

namespace App\Domains\Auth\Repositories;

use App\Domains\Workforce\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class MemberRepository
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->paginateEmployees($perPage);
    }

    public function paginateEmployees(int $perPage = 20): LengthAwarePaginator
    {
        return $this->employeeQuery()->paginate($perPage);
    }

    public function exportEmployees(callable $callback): void
    {
        $this->employeeQuery()
            ->chunkById(500, function ($members) use ($callback) {
                foreach ($members as $member) {
                    $callback($member);
                }
            });
    }

    private function employeeQuery()
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', '!=', 'customer'))
            ->with(['roles:id,name', 'teams:id,name,department_id'])
            ->orderBy('id');
    }

    public function paginateCustomers(int $perPage = 20): LengthAwarePaginator
    {
        return User::query()
            ->role('customer')
            ->with(['roles:id,name', 'contact:id,name,email'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function find(int $id): User
    {
        return User::query()->with('roles:id,name')->findOrFail($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
            ->first();
    }

    public function createMember(string $name, string $email, string $password, string $role, array $customFields = []): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'custom_fields' => $customFields ?: null,
        ]);

        $user->assignRole($role);

        return $user->load('roles:id,name');
    }

    public function syncTeams(User $user, array $teamIds): User
    {
        $user->teams()->sync(
            collect($teamIds)
                ->filter()
                ->mapWithKeys(fn ($teamId) => [(int) $teamId => ['org_role' => Team::ROLE_MEMBER]])
                ->all(),
        );

        return $user->fresh(['roles:id,name', 'teams:id,name,department_id']);
    }

    public function attachToTeam(User $user, int $teamId, string $orgRole = Team::ROLE_MEMBER): User
    {
        $user->teams()->syncWithoutDetaching([
            $teamId => ['org_role' => $orgRole],
        ]);

        return $user->fresh(['roles:id,name', 'teams:id,name,department_id']);
    }

    public function updateRole(User $user, string $role): User
    {
        $user->syncRoles([$role]);

        return $user->fresh(['roles:id,name']);
    }

    public function updateCustomFields(User $user, array $customFields): User
    {
        $user->update(['custom_fields' => $customFields ?: null]);

        return $user->fresh(['roles:id,name', 'teams:id,name,department_id']);
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function adminCount(): int
    {
        return User::role('admin')->count();
    }
}
