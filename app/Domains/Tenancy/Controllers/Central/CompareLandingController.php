<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Services\CompareLandingContentService;
use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Domains\Tenancy\Support\CompareLandingDefinition;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CompareLandingController extends Controller
{
    public function __construct(private CompareLandingContentService $content)
    {
    }

    public function show(string $competitor): Response
    {
        $pageContent = $this->content->forSlug($competitor);

        abort_unless($pageContent !== null, 404);

        $definition = CompareLandingDefinition::find($competitor) ?? [
            'slug' => $competitor,
            'seo_key' => CompareLandingDefinition::seoKey($competitor),
            'path' => CompareLandingDefinition::path($competitor),
        ];

        return Inertia::render('Central/CompareLanding', [
            ...CentralMarketingPresenter::shared(),
            'competitor' => $competitor,
            'compareMeta' => $definition,
            'content' => $pageContent,
        ]);
    }
}
