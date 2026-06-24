<?php

namespace App\Domains\Knowledge\Support;

use App\Domains\Brands\Models\Brand;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;

class HelpCenterPublicCache
{
    public static function collectionsKey(?int $brandId): string
    {
        return TenantCache::key('help_center.collections:'.($brandId ?? 'default'));
    }

    public static function featuredKey(?int $brandId, string $locale, int $limit): string
    {
        return TenantCache::key('help_center.featured:'.($brandId ?? 'default').":{$locale}:{$limit}");
    }

    public static function rememberCollections(?int $brandId, callable $callback): mixed
    {
        return self::remember(self::collectionsKey($brandId), $callback);
    }

    public static function rememberFeatured(?int $brandId, string $locale, int $limit, callable $callback): mixed
    {
        return self::remember(self::featuredKey($brandId, $locale, $limit), $callback);
    }

    private static function remember(string $key, callable $callback): mixed
    {
        if (! tenancy()->initialized) {
            return $callback();
        }

        $value = Cache::get($key);

        if ($value !== null) {
            return $value;
        }

        return Cache::lock($key.':lock', 10)->block(5, function () use ($key, $callback) {
            return Cache::remember($key, 300 + random_int(0, 30), $callback);
        });
    }

    public static function forget(): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        HelpCenterGuestCache::forget();
    }

    public static function forgetAll(): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        HelpCenterGuestCache::forget();
        self::forgetBrand(null);

        foreach (Brand::query()->pluck('id') as $brandId) {
            self::forgetBrand((int) $brandId);
        }
    }

    public static function forgetBrand(?int $brandId): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        Cache::forget(self::collectionsKey($brandId));

        foreach (['en', 'es', 'fr', 'de', 'ar'] as $locale) {
            foreach ([6, 12] as $limit) {
                Cache::forget(self::featuredKey($brandId, $locale, $limit));
            }
        }
    }
}
