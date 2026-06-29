<?php

namespace App\Domains\Tenancy\Support;

use Illuminate\Http\Request;

class MarketingSeoContext
{
    public const MARKETING_ROUTE_NAMES = [
        'central.home',
        'central.login',
        'central.register',
        'central.vertical',
        'central.compare',
        'central.compare.index',
        'central.migrate',
        'central.migrate.index',
        'central.features.index',
        'central.feature',
        'central.integration',
        'central.static.pricing',
        'central.static.about',
        'central.static.integrations',
        'central.static.industries',
        'central.static.resources',
        'central.static.contact',
        'central.static.support',
        'central.static.privacy',
        'central.static.terms',
        'central.blog.index',
        'central.blog.show',
    ];

    public static function isMarketingRequest(Request $request): bool
    {
        return $request->routeIs(...self::MARKETING_ROUTE_NAMES);
    }

    public static function pageKey(Request $request): string
    {
        return match (true) {
            $request->routeIs('central.register') => 'register',
            $request->routeIs('central.login') => 'login',
            $request->routeIs('central.vertical') => VerticalLandingDefinition::seoKey(
                (string) $request->route('vertical')
            ),
            $request->routeIs('central.compare') => CompareLandingDefinition::seoKey(
                (string) (CompareLandingDefinition::slugFromComparison((string) $request->route('comparison')) ?? '')
            ),
            $request->routeIs('central.migrate') => MigrateLandingDefinition::seoKey(
                (string) $request->route('source')
            ),
            $request->routeIs('central.features.index') => 'features_index',
            $request->routeIs('central.compare.index') => 'compare_index',
            $request->routeIs('central.migrate.index') => 'migrate_index',
            $request->routeIs('central.feature') => MarketingFeatureDefinition::seoKey(
                (string) $request->route('feature')
            ),
            $request->routeIs('central.integration') => IntegrationLandingDefinition::seoKey(
                (string) $request->route('integration')
            ),
            $request->routeIs(
                'central.static.pricing',
                'central.static.about',
                'central.static.integrations',
                'central.static.industries',
                'central.static.resources',
                'central.static.contact',
                'central.static.support',
                'central.static.privacy',
                'central.static.terms',
            ) => MarketingStaticPageDefinition::seoKey(
                match ($request->route()?->getName()) {
                    'central.static.pricing' => 'pricing',
                    'central.static.about' => 'about',
                    'central.static.integrations' => 'integrations',
                    'central.static.industries' => 'industries',
                    'central.static.resources' => 'resources',
                    'central.static.contact' => 'contact',
                    'central.static.support' => 'support',
                    'central.static.privacy' => 'privacy',
                    'central.static.terms' => 'terms',
                    default => 'pricing',
                }
            ),
            $request->routeIs('central.blog.index') => 'blog',
            $request->routeIs('central.blog.show') => MarketingBlogDefinition::seoKey(
                (string) $request->route('slug')
            ),
            default => 'home',
        };
    }
}
