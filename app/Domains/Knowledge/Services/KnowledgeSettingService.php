<?php

namespace App\Domains\Knowledge\Services;

use App\Domains\Knowledge\Repositories\KnowledgeSettingRepository;
use App\Domains\Security\Support\AuditRecorder;
use InvalidArgumentException;

class KnowledgeSettingService
{
    public const LOCALE_LABELS = [
        'en' => 'English',
        'es' => 'Español',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'pt' => 'Português',
        'it' => 'Italiano',
        'nl' => 'Nederlands',
        'ja' => '日本語',
        'zh' => '中文',
        'ar' => 'العربية',
    ];

    public function __construct(
        private KnowledgeSettingRepository $settings,
        private AuditRecorder $audit,
    ) {
    }

    public function enabledLocales(): array
    {
        $locales = $this->settings->current()->kb_locales ?? ['en'];

        return array_values(array_unique(array_filter($locales)));
    }

    public function defaultLocale(): string
    {
        $default = $this->settings->current()->kb_default_locale ?? 'en';
        $enabled = $this->enabledLocales();

        if (! in_array($default, $enabled, true)) {
            return $enabled[0] ?? 'en';
        }

        return $default;
    }

    public function localeOptions(): array
    {
        return collect($this->enabledLocales())
            ->map(fn (string $locale) => [
                'code' => $locale,
                'label' => self::LOCALE_LABELS[$locale] ?? strtoupper($locale),
            ])
            ->values()
            ->all();
    }

    public function allLocaleChoices(): array
    {
        return collect(self::LOCALE_LABELS)
            ->map(fn (string $label, string $code) => ['code' => $code, 'label' => $label])
            ->values()
            ->all();
    }

    public function snapshot(): array
    {
        return [
            'kb_locales' => $this->enabledLocales(),
            'kb_default_locale' => $this->defaultLocale(),
            'locale_choices' => $this->allLocaleChoices(),
        ];
    }

    public function update(array $data): array
    {
        $locales = collect($data['kb_locales'] ?? [])
            ->map(fn ($locale) => strtolower(trim((string) $locale)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($locales === []) {
            throw new InvalidArgumentException('At least one locale is required.');
        }

        $default = strtolower(trim((string) ($data['kb_default_locale'] ?? $locales[0])));

        if (! in_array($default, $locales, true)) {
            throw new InvalidArgumentException('Default locale must be enabled.');
        }

        $setting = $this->settings->update($this->settings->current(), [
            'kb_locales' => $locales,
            'kb_default_locale' => $default,
        ]);

        $this->audit->record('settings.knowledge_locales_updated', null, [
            'kb_locales' => $setting->kb_locales,
            'kb_default_locale' => $setting->kb_default_locale,
        ]);

        return $this->snapshot();
    }
}
