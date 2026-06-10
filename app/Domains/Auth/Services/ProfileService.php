<?php

namespace App\Domains\Auth\Services;

use App\Models\User;
use App\Domains\Security\Support\AuditRecorder;
use App\Support\LocaleSupport;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileService
{
    public function __construct(private AuditRecorder $audit)
    {
    }

    public function update(User $user, array $data): User
    {
        if (isset($data['email']) && $data['email'] !== $user->email) {
            $exists = User::query()
                ->where('email', $data['email'])
                ->where('id', '!=', $user->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'email' => 'This email is already in use.',
                ]);
            }
        }

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if (array_key_exists('locale', $data)) {
            $payload['locale'] = LocaleSupport::resolve($data['locale']);
        }

        if (array_key_exists('timezone', $data)) {
            $payload['timezone'] = $data['timezone'] ?: null;
        }

        $user->update($payload);

        $this->audit->record('profile.updated', $user, [
            'email' => $user->email,
            'locale' => $user->locale,
            'timezone' => $user->timezone,
        ], $user->id);

        return $user->fresh();
    }

    public function updatePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        $this->audit->record('profile.password_updated', $user, [], $user->id);
    }
}
