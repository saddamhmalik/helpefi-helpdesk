<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Services\MarketingStaticContentService;
use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Domains\Tenancy\Support\MarketingStaticPageDefinition;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class MarketingStaticPageController extends Controller
{
    public function __construct(private MarketingStaticContentService $content)
    {
    }

    public function show(string $page): Response
    {
        $definition = MarketingStaticPageDefinition::find($page);
        $pageContent = $this->content->forSlug($page, config('marketing_seo.organization.contact_email'));

        abort_unless($definition !== null && $pageContent !== null, 404);

        return Inertia::render('Central/MarketingStaticPage', [
            ...CentralMarketingPresenter::shared(),
            'page' => $page,
            'pageMeta' => $definition,
            'content' => $pageContent,
            'pricingMeta' => $page === 'pricing'
                ? app(MarketingStaticContentService::class)->homePlanMeta()
                : null,
        ]);
    }
}
