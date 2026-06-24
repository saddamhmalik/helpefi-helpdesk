<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Services\MarketingHomeContentService;
use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Central/Home', [
            ...CentralMarketingPresenter::shared(),
            'homeContent' => app(MarketingHomeContentService::class)->content(),
        ]);
    }
}
