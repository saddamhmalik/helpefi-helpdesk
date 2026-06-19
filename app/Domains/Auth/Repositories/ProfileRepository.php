<?php

namespace App\Domains\Auth\Repositories;

use App\Domains\Tenancy\Services\TenantStorageResolver;
use App\Models\User;
use App\Support\AvatarSupport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ProfileRepository
{
    public function __construct(private TenantStorageResolver $storage)
    {
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }

    public function storeAvatar(User $user, UploadedFile $file): User
    {
        $this->deleteStoredAvatar($user);

        $diskName = $this->storage->diskName();
        $extension = $this->extensionForMime((string) $file->getMimeType());
        $path = $file->storeAs(
            'user-avatars/'.$user->id,
            Str::uuid().'.'.$extension,
            $diskName,
        );

        $user->update([
            'avatar_type' => 'upload',
            'avatar_path' => $path,
            'avatar_disk' => $diskName,
        ]);

        return $user->fresh();
    }

    public function clearAvatar(User $user, string $avatarType = AvatarSupport::DEFAULT): User
    {
        $this->deleteStoredAvatar($user);

        $user->update([
            'avatar_type' => AvatarSupport::resolveType($avatarType),
            'avatar_path' => null,
            'avatar_disk' => null,
        ]);

        return $user->fresh();
    }

    public function deleteStoredAvatar(User $user): void
    {
        if (! filled($user->avatar_path)) {
            return;
        }

        $this->storage->delete($user->avatar_path, $user->avatar_disk);
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
