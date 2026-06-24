<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Domains\Tenancy\Support\MarketingFeatureDefinition;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class FeatureLandingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Central/FeaturesIndex', CentralMarketingPresenter::shared());
    }

    public function show(string $feature): Response
    {
        $definition = MarketingFeatureDefinition::find($feature);

        abort_unless($definition !== null, 404);

        return Inertia::render('Central/FeatureLanding', [
            ...CentralMarketingPresenter::shared(),
            'feature' => $feature,
            'featureMeta' => $definition,
        ]);
    }
}
