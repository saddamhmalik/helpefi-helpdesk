<?php

namespace App\Domains\Tenancy\Controllers;

use App\Domains\Tenancy\Services\TenantDummyDataService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantDummyDataController extends Controller
{
    public function __construct(private TenantDummyDataService $dummyData)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $this->dummyData->install($request->user());

        return redirect()
            ->route('workspace.index')
            ->with('success', 'Sample data loaded. Explore tickets, customers, and teams — remove it anytime with one click.');
    }

    public function skip(): RedirectResponse
    {
        $this->dummyData->skip();

        return back()->with('success', 'Starting with an empty workspace.');
    }

    public function destroy(): RedirectResponse
    {
        $this->dummyData->remove();

        return redirect()
            ->route('setup')
            ->with('success', 'Sample data removed. Continue workspace setup when you are ready.');
    }
}
