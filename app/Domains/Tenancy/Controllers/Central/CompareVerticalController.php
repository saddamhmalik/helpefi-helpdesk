<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Services\CompareVerticalContentService;
use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Domains\Tenancy\Support\CompareVerticalDefinition;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CompareVerticalController extends Controller
{
    public function __construct(private CompareVerticalContentService $content)
    {
    }

    public function show(string $competitor, string $vertical): Response
    {
        $pageContent = $this->content->for($competitor, $vertical);

        abort_unless($pageContent !== null, 404);

        $definition = CompareVerticalDefinition::find($competitor, $vertical) ?? [
            'competitor' => $competitor,
            'vertical' => $vertical,
            'seo_key' => CompareVerticalDefinition::seoKey($competitor, $vertical),
            'path' => CompareVerticalDefinition::path($competitor, $vertical),
            'accent' => 'blue',
        ];

        return Inertia::render('Central/CompareLanding', [
            ...CentralMarketingPresenter::shared(),
            'competitor' => $competitor,
            'compareMeta' => $definition,
            'content' => $pageContent,
            '_seo_page_key' => $definition['seo_key'],
        ]);
    }
}
