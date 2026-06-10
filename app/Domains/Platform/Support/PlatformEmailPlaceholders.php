<?php

namespace App\Domains\Platform\Support;

class PlatformEmailPlaceholders
{
    public static function definitions(): array
    {
        return [
            ['key' => 'brand', 'label' => 'Platform name', 'example' => 'helpefi'],
            ['key' => 'admin_name', 'label' => 'Registrant name', 'example' => 'Jane Admin'],
            ['key' => 'admin_email', 'label' => 'Registrant email', 'example' => 'jane@company.com'],
            ['key' => 'organization_name', 'label' => 'Organization name', 'example' => 'Acme Support'],
            ['key' => 'workspace_slug', 'label' => 'Workspace slug', 'example' => 'acme'],
            ['key' => 'workspace_url', 'label' => 'Workspace URL', 'example' => 'https://acme.helpdesk.test'],
            ['key' => 'welcome_url', 'label' => 'Welcome sign-in link', 'example' => 'https://acme.helpdesk.test/welcome?token=...'],
            ['key' => 'trial_days', 'label' => 'Trial length (days)', 'example' => '14'],
            ['key' => 'central_domain', 'label' => 'Central domain', 'example' => 'helpdesk.test'],
        ];
    }

    public static function keys(): array
    {
        return array_column(self::definitions(), 'key');
    }

    public static function render(string $content, array $variables): string
    {
        $replacements = [];

        foreach ($variables as $key => $value) {
            $replacements['{{'.$key.'}}'] = (string) $value;
        }

        return strtr($content, $replacements);
    }
}
