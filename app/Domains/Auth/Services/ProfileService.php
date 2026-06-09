<?php

namespace App\Domains\Auth\Services;

use App\Models\User;
use App\Domains\Security\Support\AuditRecorder;
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

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        $this->audit->record('profile.updated', $user, [
            'email' => $user->email,
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
