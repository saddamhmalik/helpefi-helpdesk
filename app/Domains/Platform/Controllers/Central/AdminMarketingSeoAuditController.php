<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Services\MarketingSeoAuditService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminMarketingSeoAuditController extends Controller
{
    public function __construct(private MarketingSeoAuditService $audit)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Central/Admin/Seo/Audit', [
            'report' => $this->audit->run(),
        ]);
    }

    public function store(): RedirectResponse
    {
        $this->audit->run(fresh: true);

        return redirect()->route('central.admin.seo.audit')->with('success', 'SEO audit completed.');
    }
}
