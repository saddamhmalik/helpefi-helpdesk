<?php

namespace App\Domains\Tenancy\Support;

use App\Domains\Platform\Services\MarketingPageContentService;
use App\Domains\Platform\Support\MarketingContentType;

class CompareVerticalDefinition
{
    public static function find(string $competitor, string $vertical): ?array
    {
        $compareConfig = config("marketing_comparisons.{$competitor}");
        $verticalConfig = config("marketing_verticals.{$vertical}");

        if (! is_array($compareConfig) || ! is_array($verticalConfig)) {
            return null;
        }

        return [
            'competitor' => $competitor,
            'vertical' => $vertical,
            'seo_key' => self::seoKey($competitor, $vertical),
            'path' => self::path($competitor, $vertical),
            'accent' => $compareConfig['accent'] ?? 'blue',
        ];
    }

    public static function allCombinations(): array
    {
        $competitors = array_keys(config('marketing_comparisons', []));
        $verticals = array_keys(config('marketing_verticals', []));
        $combinations = [];

        foreach ($competitors as $competitor) {
            foreach ($verticals as $vertical) {
                $entry = self::find($competitor, $vertical);
                if ($entry !== null) {
                    $combinations[] = $entry;
                }
            }
        }

        return $combinations;
    }

    public static function path(string $competitor, string $vertical): string
    {
        return '/compare/'.$competitor.'/for/'.$vertical;
    }

    public static function seoKey(string $competitor, string $vertical): string
    {
        return 'compare_'.str_replace('-', '_', $competitor).'_for_'.str_replace('-', '_', $vertical);
    }

    public static function slugFromSeoKey(string $seoKey): ?array
    {
        if (! str_starts_with($seoKey, 'compare_') || ! str_contains($seoKey, '_for_')) {
            return null;
        }

        $rest = substr($seoKey, strlen('compare_'));
        $parts = explode('_for_', $rest, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$competitorKey, $verticalKey] = $parts;
        $competitor = str_replace('_', '-', $competitorKey);
        $vertical = str_replace('_', '-', $verticalKey);

        if (self::find($competitor, $vertical) !== null) {
            return ['competitor' => $competitor, 'vertical' => $vertical];
        }

        $pageContent = app(MarketingPageContentService::class);

        if ($pageContent->isKnownSlug(MarketingContentType::COMPARISON, $competitor)
            && $pageContent->isKnownSlug(MarketingContentType::VERTICAL, $vertical)) {
            return ['competitor' => $competitor, 'vertical' => $vertical];
        }

        return null;
    }

    public static function isValid(string $competitor, string $vertical): bool
    {
        return self::find($competitor, $vertical) !== null;
    }
}
