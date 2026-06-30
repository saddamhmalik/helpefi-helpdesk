<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Jobs\RunMarketingSeoAuditJob;
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
            'report' => $this->audit->cachedReport(),
            'auditStatus' => $this->audit->auditStatus(),
        ]);
    }

    public function store(): RedirectResponse
    {
        if ($this->audit->isRunning()) {
            return redirect()
                ->route('central.admin.seo.audit')
                ->with('warning', 'SEO audit is already running.');
        }

        $this->audit->markRunning();
        RunMarketingSeoAuditJob::dispatch();

        return redirect()
            ->route('central.admin.seo.audit')
            ->with('success', 'SEO audit started. Results will appear shortly.');
    }
}
