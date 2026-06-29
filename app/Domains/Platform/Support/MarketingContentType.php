<?php

namespace App\Domains\Platform\Support;

class MarketingContentType
{
    public const FEATURE = 'feature';

    public const VERTICAL = 'vertical';

    public const COMPARISON = 'comparison';

    public const INTEGRATION = 'integration';

    public const LANDING = 'landing';

    public const BLOG_OUTLINE = 'blog_outline';

    public static function all(): array
    {
        return [
            self::FEATURE,
            self::VERTICAL,
            self::COMPARISON,
            self::INTEGRATION,
            self::LANDING,
            self::BLOG_OUTLINE,
        ];
    }

    public static function pageTypes(): array
    {
        return [
            self::FEATURE,
            self::VERTICAL,
            self::COMPARISON,
            self::INTEGRATION,
            self::LANDING,
        ];
    }

    public static function configKey(string $type): ?string
    {
        return match ($type) {
            self::FEATURE, self::LANDING => 'marketing_feature_content',
            self::VERTICAL => 'marketing_vertical_content',
            self::COMPARISON => 'marketing_comparison_content',
            self::INTEGRATION => 'marketing_integration_content',
            default => null,
        };
    }

    public static function registryKey(string $type): ?string
    {
        return match ($type) {
            self::FEATURE, self::LANDING => 'marketing_features',
            self::VERTICAL => 'marketing_verticals',
            self::COMPARISON => 'marketing_comparisons',
            self::INTEGRATION => 'marketing_integrations',
            default => null,
        };
    }

    public static function seoKeyPrefix(string $type): ?string
    {
        return match ($type) {
            self::FEATURE, self::LANDING => 'feature_',
            self::VERTICAL => 'vertical_',
            self::COMPARISON => 'compare_',
            self::INTEGRATION => 'integration_',
            default => null,
        };
    }

    public static function label(string $type): string
    {
        return match ($type) {
            self::FEATURE => 'Feature page',
            self::VERTICAL => 'Industry page',
            self::COMPARISON => 'Comparison page',
            self::INTEGRATION => 'Integration page',
            self::LANDING => 'Landing page',
            self::BLOG_OUTLINE => 'Blog outline',
            default => $type,
        };
    }
}
