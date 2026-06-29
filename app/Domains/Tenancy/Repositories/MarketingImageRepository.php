<?php

namespace App\Domains\Tenancy\Repositories;

use Illuminate\Support\Facades\File;

class MarketingImageRepository
{
    public function publicImageAbsolutePath(string $publicRelativePath): ?string
    {
        $relative = ltrim($publicRelativePath, '/');

        if ($relative === '' || str_contains($relative, "\0")) {
            return null;
        }

        $candidate = public_path($relative);
        $real = realpath($candidate);
        $publicRoot = realpath(public_path());

        if (! is_string($real) || ! is_string($publicRoot)) {
            return null;
        }

        if (! str_starts_with($real, $publicRoot.DIRECTORY_SEPARATOR)) {
            return null;
        }

        if (! is_file($real)) {
            return null;
        }

        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'avif', 'gif'], true)) {
            return null;
        }

        return $real;
    }

    public function cacheAbsolutePath(string $key, string $extension): string
    {
        $safeExt = strtolower(preg_replace('/[^a-z0-9]+/i', '', $extension) ?: 'img');
        $safeKey = preg_replace('/[^a-z0-9]+/i', '', $key) ?: 'cache';

        $dir = storage_path('app/marketing-image-cache/'.substr($safeKey, 0, 2).'/'.substr($safeKey, 2, 2));
        File::ensureDirectoryExists($dir);

        return $dir.'/'.$safeKey.'.'.$safeExt;
    }

    public function hasCached(string $absolutePath): bool
    {
        return is_file($absolutePath) && filesize($absolutePath) > 0;
    }

    public function readBytes(string $absolutePath): string
    {
        return (string) file_get_contents($absolutePath);
    }
}

