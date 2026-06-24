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
            ['key' => 'workspace_url', 'label' => 'Workspace URL', 'example' => 'https://acme.helpefi.com'],
            ['key' => 'welcome_url', 'label' => 'Welcome sign-in link', 'example' => 'https://acme.helpefi.com/welcome?token=...'],
            ['key' => 'verification_url', 'label' => 'Email verification link', 'example' => 'https://helpefi.com/register/verify/...'],
            ['key' => 'trial_days', 'label' => 'Trial length (days)', 'example' => '14'],
            ['key' => 'trial_days_remaining', 'label' => 'Trial days remaining', 'example' => '7'],
            ['key' => 'central_domain', 'label' => 'Central domain', 'example' => 'helpefi.com'],
            ['key' => 'setup_url', 'label' => 'Setup wizard URL', 'example' => 'https://acme.helpefi.com/setup'],
            ['key' => 'billing_url', 'label' => 'Billing settings URL', 'example' => 'https://acme.helpefi.com/settings/billing'],
            ['key' => 'pricing_url', 'label' => 'Marketing pricing page', 'example' => 'https://helpefi.com/pricing'],
            ['key' => 'access_ends_at', 'label' => 'Subscription access end date', 'example' => 'July 15, 2026'],
            ['key' => 'grace_days_remaining', 'label' => 'Days until access ends', 'example' => '3'],
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
