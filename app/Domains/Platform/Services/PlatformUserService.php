<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Models\PlatformRole;
use App\Domains\Platform\Repositories\PlatformUserRepository;
use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Models\PlatformUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PlatformUserService
{
    public function __construct(
        private PlatformUserRepository $users,
        private PlatformAuditRecorder $audit,
    ) {
    }

    public function list(int $perPage = 20): LengthAwarePaginator
    {
        return $this->users->paginate($perPage);
    }

    public function show(int $id): PlatformUser
    {
        return $this->users->find($id);
    }

    public function create(array $data): PlatformUser
    {
        $this->assertUniqueEmail($data['email']);
        $this->assertValidRoles($data['roles'] ?? []);

        $user = $this->users->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        $user->syncRoles($data['roles'] ?? []);

        $user = $user->fresh(['roles']);

        $this->audit->record('platform.user.created', $user, [
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->all(),
        ]);

        return $user;
    }

    public function update(int $id, array $data): PlatformUser
    {
        $user = $this->users->find($id);

        if (isset($data['email']) && $data['email'] !== $user->email) {
            $this->assertUniqueEmail($data['email'], $user->id);
        }

        if (isset($data['roles'])) {
            $this->assertValidRoles($data['roles']);
        }

        $payload = [];

        if (array_key_exists('name', $data)) {
            $payload['name'] = $data['name'];
        }

        if (array_key_exists('email', $data)) {
            $payload['email'] = $data['email'];
        }

        if (array_key_exists('is_active', $data)) {
            $payload['is_active'] = (bool) $data['is_active'];
        }

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        if ($payload !== []) {
            $user = $this->users->update($user, $payload);
        }

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        $user = $user->fresh(['roles']);

        $this->audit->record('platform.user.updated', $user, [
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->all(),
        ]);

        return $user;
    }

    public function delete(int $id, PlatformUser $actor): void
    {
        $user = $this->users->find($id);

        if ($user->id === $actor->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        $this->audit->record('platform.user.deleted', $user, [
            'email' => $user->email,
        ]);

        $this->users->delete($user);
    }

    public function validationRules(bool $creating = false): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => $creating
                ? ['required', 'confirmed', Password::defaults()]
                : ['nullable', 'confirmed', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', 'max:100'],
        ];
    }

    private function assertUniqueEmail(string $email, ?int $ignoreId = null): void
    {
        $existing = $this->users->findByEmail($email);

        if ($existing && $existing->id !== $ignoreId) {
            throw ValidationException::withMessages([
                'email' => 'This email is already in use.',
            ]);
        }
    }

    private function assertValidRoles(array $roles): void
    {
        foreach ($roles as $roleName) {
            if (! PlatformRole::query()->where('name', $roleName)->exists()) {
                throw ValidationException::withMessages([
                    'roles' => "Unknown role: {$roleName}",
                ]);
            }
        }
    }
}
