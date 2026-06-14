<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class AvatarSupport
{
    public const TYPES = ['initials', 'gravatar', 'upload'];

    public const DEFAULT = 'initials';

    public const USER_COLUMNS = ['id', 'name', 'email', 'avatar_type', 'avatar_path'];

    public static function resolveType(?string $type): string
    {
        $normalized = strtolower(trim((string) $type));

        return in_array($normalized, self::TYPES, true) ? $normalized : self::DEFAULT;
    }

    public static function options(): array
    {
        return collect(self::TYPES)
            ->map(fn (string $value) => ['value' => $value])
            ->all();
    }

    public static function url(User $user): ?string
    {
        $type = self::resolveType($user->avatar_type ?? null);

        if ($type === 'gravatar') {
            return self::gravatarUrl((string) $user->email);
        }

        if ($type === 'upload' && filled($user->avatar_path)) {
            return Storage::disk('public')->url($user->avatar_path);
        }

        return null;
    }

    public static function gravatarUrl(string $email, int $size = 256): string
    {
        $hash = md5(strtolower(trim($email)));

        return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=404";
    }

    public static function payload(User $user): array
    {
        return [
            'avatar_type' => self::resolveType($user->avatar_type ?? null),
            'avatar_url' => self::url($user),
        ];
    }
}
