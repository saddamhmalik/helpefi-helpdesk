<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Repositories\PlatformUserRepository;
use App\Models\PlatformUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PlatformProfileService
{
    public function __construct(private PlatformUserRepository $users)
    {
    }

    public function update(PlatformUser $user, array $data): PlatformUser
    {
        if (isset($data['email']) && $data['email'] !== $user->email) {
            $existing = $this->users->findByEmail($data['email']);

            if ($existing && $existing->id !== $user->id) {
                throw ValidationException::withMessages([
                    'email' => 'This email is already in use.',
                ]);
            }
        }

        return $this->users->update($user, [
            'name' => $data['name'],
            'email' => $data['email'],
        ]);
    }

    public function updatePassword(PlatformUser $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $this->users->update($user, [
            'password' => Hash::make($newPassword),
        ]);
    }
}
