<?php

namespace App\Support;

class LocaleSupport
{
    public const APP_LOCALES = ['en', 'ar', 'es', 'fr', 'de'];

    public const APP_LOCALE_COOKIE = 'app_locale';

    public const LABELS = [
        'en' => 'English',
        'ar' => 'العربية',
        'es' => 'Español',
        'fr' => 'Français',
        'de' => 'Deutsch',
    ];

    public const RTL_LOCALES = ['ar'];

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::APP_LOCALES, true);
    }

    public static function isRtl(string $locale): bool
    {
        return in_array($locale, self::RTL_LOCALES, true);
    }

    public static function resolve(?string $locale): string
    {
        $normalized = strtolower(trim((string) $locale));

        return self::isSupported($normalized) ? $normalized : 'en';
    }

    public static function resolveFromRequest(\Illuminate\Http\Request $request): string
    {
        $cookie = $request->cookie(self::APP_LOCALE_COOKIE);

        if ($cookie) {
            return self::resolve($cookie);
        }

        return self::resolve(config('app.locale'));
    }

    public static function options(): array
    {
        return collect(self::APP_LOCALES)
            ->map(fn (string $code) => [
                'code' => $code,
                'label' => self::LABELS[$code],
                'rtl' => self::isRtl($code),
            ])
            ->values()
            ->all();
    }
}
