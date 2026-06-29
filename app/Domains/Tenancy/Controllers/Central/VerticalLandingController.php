<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Services\VerticalLandingContentService;
use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Domains\Tenancy\Support\VerticalLandingDefinition;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class VerticalLandingController extends Controller
{
    public function __construct(private VerticalLandingContentService $content)
    {
    }

    public function show(string $vertical): Response
    {
        $pageContent = $this->content->forSlug($vertical);

        abort_unless($pageContent !== null, 404);

        $definition = VerticalLandingDefinition::find($vertical) ?? [
            'slug' => $vertical,
            'seo_key' => VerticalLandingDefinition::seoKey($vertical),
            'path' => VerticalLandingDefinition::path($vertical),
        ];

        return Inertia::render('Central/VerticalLanding', [
            ...CentralMarketingPresenter::shared(),
            'vertical' => $vertical,
            'verticalMeta' => $definition,
            'content' => $pageContent,
        ]);
    }
}
