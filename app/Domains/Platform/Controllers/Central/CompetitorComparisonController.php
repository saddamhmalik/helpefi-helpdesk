<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Services\CompetitorComparisonService;
use App\Domains\Tenancy\Controllers\Central\CompareLandingController;
use App\Domains\Tenancy\Services\CompareLandingContentService;
use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Domains\Tenancy\Support\CompareLandingDefinition;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CompetitorComparisonController extends Controller
{
    public function __construct(
        private readonly CompetitorComparisonService $comparisons,
        private readonly CompareLandingContentService $content,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Central/CompareIndex', [
            ...CentralMarketingPresenter::shared(),
            'compareHub' => $this->content->hub(),
        ]);
    }

    public function show(string $comparison): Response
    {
        $slug = CompareLandingDefinition::slugFromComparison($comparison);

        abort_unless($slug !== null, 404);

        $payload = $this->comparisons->forSlug($slug);

        if ($payload !== null) {
            return Inertia::render('Central/CompetitorComparison', [
                ...CentralMarketingPresenter::shared(),
                'competitorSlug' => $slug,
                ...$payload,
            ]);
        }

        return app(CompareLandingController::class)->show($slug);
    }
}

