<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Repositories\ProfileRepository;
use App\Domains\Security\Support\AuditRecorder;
use App\Models\User;
use App\Support\AppearanceSupport;
use App\Support\AvatarSupport;
use App\Support\LocaleSupport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileService
{
    public function __construct(
        private ProfileRepository $profiles,
        private AuditRecorder $audit,
    ) {
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

        if (array_key_exists('appearance', $data)) {
            $payload['appearance'] = AppearanceSupport::resolve($data['appearance']);
        }

        if (array_key_exists('avatar_type', $data)) {
            $nextType = AvatarSupport::resolveType($data['avatar_type']);
            $currentType = AvatarSupport::resolveType($user->avatar_type);

            if ($nextType !== 'upload' && $currentType === 'upload') {
                $this->profiles->deleteStoredAvatar($user);
                $payload['avatar_path'] = null;
            }

            $payload['avatar_type'] = $nextType;
        }

        $user = $this->profiles->update($user, $payload);

        $this->audit->record('profile.updated', $user, [
            'email' => $user->email,
            'locale' => $user->locale,
            'timezone' => $user->timezone,
            'appearance' => $user->appearance,
            'avatar_type' => $user->avatar_type,
        ], $user->id);

        return $user;
    }

    public function updateAppearance(User $user, string $appearance): User
    {
        $user = $this->profiles->update($user, [
            'appearance' => AppearanceSupport::resolve($appearance),
        ]);

        $this->audit->record('profile.appearance_updated', $user, [
            'appearance' => $user->appearance,
        ], $user->id);

        return $user;
    }

    public function updateLocale(User $user, string $locale): User
    {
        $user = $this->profiles->update($user, [
            'locale' => LocaleSupport::resolve($locale),
        ]);

        $this->audit->record('profile.locale_updated', $user, [
            'locale' => $user->locale,
        ], $user->id);

        return $user;
    }

    public function updatePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $this->profiles->update($user, [
            'password' => Hash::make($newPassword),
        ]);

        $this->audit->record('profile.password_updated', $user, [], $user->id);
    }

    public function uploadAvatar(User $user, UploadedFile $file): User
    {
        $user = $this->profiles->storeAvatar($user, $file);

        $this->audit->record('profile.avatar_uploaded', $user, [
            'avatar_type' => $user->avatar_type,
        ], $user->id);

        return $user;
    }

    public function removeAvatar(User $user): User
    {
        $user = $this->profiles->clearAvatar($user);

        $this->audit->record('profile.avatar_removed', $user, [
            'avatar_type' => $user->avatar_type,
        ], $user->id);

        return $user;
    }
}
