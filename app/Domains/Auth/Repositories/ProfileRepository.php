<?php

namespace App\Domains\Auth\Repositories;

use App\Models\User;
use App\Support\AvatarSupport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileRepository
{
    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }

    public function storeAvatar(User $user, UploadedFile $file): User
    {
        $this->deleteStoredAvatar($user);

        $extension = $this->extensionForMime((string) $file->getMimeType());
        $path = $file->storeAs(
            'user-avatars/'.$user->id,
            Str::uuid().'.'.$extension,
            'public',
        );

        $user->update([
            'avatar_type' => 'upload',
            'avatar_path' => $path,
        ]);

        return $user->fresh();
    }

    public function clearAvatar(User $user, string $avatarType = AvatarSupport::DEFAULT): User
    {
        $this->deleteStoredAvatar($user);

        $user->update([
            'avatar_type' => AvatarSupport::resolveType($avatarType),
            'avatar_path' => null,
        ]);

        return $user->fresh();
    }

    public function deleteStoredAvatar(User $user): void
    {
        if (! filled($user->avatar_path)) {
            return;
        }

        Storage::disk('public')->delete($user->avatar_path);
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
