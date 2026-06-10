<?php

namespace App\Domains\Tenancy\Controllers;

use App\Domains\Tenancy\Services\TenantDummyDataService;
use App\Domains\Tenancy\Services\TenantSetupService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    public function __construct(
        private TenantSetupService $setup,
        private TenantDummyDataService $dummyData,
    ) {
    }

    public function index(): Response|RedirectResponse
    {
        if (! tenant('id')) {
            abort(404);
        }

        return Inertia::render('Setup/Index', [
            'guide' => $this->setup->snapshot(),
            'welcome' => (bool) session('welcome'),
            'dummyData' => $this->dummyData->publicState(),
        ]);
    }

    public function completeStep(string $step): RedirectResponse
    {
        if ($this->dummyData->isActive()) {
            return back()->withErrors([
                'setup' => 'Finish exploring sample data first. Remove it when you are ready to configure your workspace.',
            ]);
        }

        $this->setup->completeStep($step);

        return back();
    }

    public function finish(): RedirectResponse
    {
        if ($this->dummyData->isActive()) {
            return back()->withErrors([
                'setup' => 'Finish exploring sample data first. Remove it when you are ready to configure your workspace.',
            ]);
        }

        $this->setup->finish();

        return redirect()->route('dashboard')->with('success', 'Setup complete. Your helpdesk is ready.');
    }
}
