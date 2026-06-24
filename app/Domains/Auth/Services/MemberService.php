<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Repositories\MemberRepository;
use App\Domains\Auth\Repositories\RoleRepository;
use App\Domains\Billing\Contracts\FeatureEntitlementChecker;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Settings\Services\HelpdeskSettingService;
use App\Domains\Workforce\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MemberService
{
    public function __construct(
        private MemberRepository $members,
        private FeatureEntitlementChecker $entitlements,
        private AuditRecorder $audit,
        private HelpdeskSettingService $helpdeskSettings,
        private RoleRepository $roles,
    ) {
    }

    public function fieldDefinitions(): array
    {
        return $this->helpdeskSettings->userFieldDefinitions();
    }

    public function list(int $perPage = 20): LengthAwarePaginator
    {
        return $this->listEmployees($perPage);
    }

    public function listEmployees(int $perPage = 20): LengthAwarePaginator
    {
        return $this->members->paginateEmployees($perPage);
    }

    public function listCustomers(int $perPage = 20): LengthAwarePaginator
    {
        return $this->members->paginateCustomers($perPage);
    }

    public function create(string $name, string $email, string $role, User $actor, array $customFields = [], ?int $teamId = null): User
    {
        $email = strtolower(trim($email));

        if ($this->members->findByEmail($email)) {
            throw ValidationException::withMessages([
                'email' => 'A user with this email already exists.',
            ]);
        }

        if ($this->roles->roleConsumesAgentSeat($role)) {
            $this->entitlements->assertLimit('agents', 1);
        }

        $member = $this->members->createMember(
            $name,
            $email,
            Str::password(32),
            $role,
            $this->helpdeskSettings->resolveFieldValues('user', $customFields),
        );

        if ($teamId) {
            $member = $this->members->attachToTeam($member, $teamId);
        }

        $this->audit->record('member.created', $member, [
            'email' => $member->email,
            'role' => $role,
            'team_id' => $teamId,
        ], $actor->id);

        return $member;
    }

    public function updateRole(int $memberId, string $role, User $actor): User
    {
        $member = $this->members->find($memberId);

        if ($member->hasRole('customer')) {
            throw ValidationException::withMessages([
                'role' => 'Customer accounts are managed separately.',
            ]);
        }

        if ($member->id === $actor->id) {
            throw ValidationException::withMessages([
                'role' => 'You cannot change your own role.',
            ]);
        }

        if ($member->hasRole('admin') && $role !== 'admin' && $this->members->adminCount() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'At least one admin is required.',
            ]);
        }

        if ($this->roles->roleConsumesAgentSeat($role) && ! $member->hasAnyRole(['admin', 'agent']) && ! $member->can('access.agent')) {
            $this->entitlements->assertLimit('agents', 1);
        }

        $updated = $this->members->updateRole($member, $role);

        $this->audit->record('member.role_updated', $updated, [
            'email' => $updated->email,
            'role' => $role,
        ], $actor->id);

        return $updated;
    }

    public function updateCustomFields(int $memberId, array $customFields, User $actor): User
    {
        $member = $this->members->find($memberId);

        if ($member->hasRole('customer')) {
            throw ValidationException::withMessages([
                'custom_fields' => 'Customer accounts are managed separately.',
            ]);
        }

        $validated = $this->helpdeskSettings->resolveFieldValues('user', $customFields);
        $updated = $this->members->updateCustomFields($member, $validated);

        $this->audit->record('member.custom_fields_updated', $updated, [
            'email' => $updated->email,
        ], $actor->id);

        return $updated;
    }

    public function updateTeams(int $memberId, array $teamIds, User $actor): User
    {
        $member = $this->members->find($memberId);

        if ($member->hasRole('customer')) {
            throw ValidationException::withMessages([
                'teams' => 'Customer accounts are managed separately.',
            ]);
        }

        $updated = $this->members->syncTeams($member, $teamIds);

        $this->audit->record('member.teams_updated', $updated, [
            'email' => $updated->email,
            'team_ids' => $teamIds,
        ], $actor->id);

        return $updated;
    }

    public function remove(int $memberId, User $actor): void
    {
        if ($memberId === $actor->id) {
            throw ValidationException::withMessages([
                'member' => 'You cannot remove yourself.',
            ]);
        }

        $member = $this->members->find($memberId);

        if ($member->hasRole('admin') && $this->members->adminCount() <= 1) {
            throw ValidationException::withMessages([
                'member' => 'At least one admin is required.',
            ]);
        }

        $this->members->delete($member);

        $this->audit->record('member.removed', null, [
            'email' => $member->email,
            'member_id' => $member->id,
        ], $actor->id);
    }

    public function removeCustomer(int $customerId, User $actor): void
    {
        $customer = $this->members->find($customerId);

        if (! $customer->hasRole('customer')) {
            throw ValidationException::withMessages([
                'customer' => 'This account is not a customer portal user.',
            ]);
        }

        $this->members->delete($customer);

        $this->audit->record('member.removed', null, [
            'email' => $customer->email,
            'member_id' => $customer->id,
            'type' => 'customer',
        ], $actor->id);
    }
}
