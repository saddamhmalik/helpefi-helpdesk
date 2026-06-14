<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Domains\Tenancy\Support\MarketingStaticPageDefinition;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class MarketingStaticPageController extends Controller
{
    public function show(string $page): Response
    {
        $definition = MarketingStaticPageDefinition::find($page);

        abort_unless($definition !== null, 404);

        return Inertia::render('Central/MarketingStaticPage', [
            ...CentralMarketingPresenter::shared(),
            'page' => $page,
            'pageMeta' => $definition,
        ]);
    }
}
