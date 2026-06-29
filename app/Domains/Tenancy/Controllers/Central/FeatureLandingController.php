<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Services\FeatureLandingContentService;
use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Domains\Tenancy\Support\MarketingFeatureDefinition;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class FeatureLandingController extends Controller
{
    public function __construct(private FeatureLandingContentService $content)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Central/FeaturesIndex', CentralMarketingPresenter::shared());
    }

    public function show(string $feature): Response
    {
        $pageContent = $this->content->forSlug($feature);

        abort_unless($pageContent !== null, 404);

        $definition = MarketingFeatureDefinition::find($feature) ?? [
            'slug' => $feature,
            'seo_key' => MarketingFeatureDefinition::seoKey($feature),
            'path' => MarketingFeatureDefinition::path($feature),
        ];

        return Inertia::render('Central/FeatureLanding', [
            ...CentralMarketingPresenter::shared(),
            'feature' => $feature,
            'featureMeta' => $definition,
            'content' => $pageContent,
        ]);
    }
}
