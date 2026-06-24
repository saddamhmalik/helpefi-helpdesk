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
        $definition = CompareLandingDefinition::find($competitor);
        $pageContent = $this->content->forSlug($competitor);

        abort_unless($definition !== null && $pageContent !== null, 404);

        return Inertia::render('Central/CompareLanding', [
            ...CentralMarketingPresenter::shared(),
            'competitor' => $competitor,
            'compareMeta' => $definition,
            'content' => $pageContent,
        ]);
    }
}
