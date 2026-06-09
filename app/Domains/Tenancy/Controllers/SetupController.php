<?php

namespace App\Domains\Tenancy\Controllers;

use App\Domains\Tenancy\Services\TenantSetupService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    public function __construct(private TenantSetupService $setup)
    {
    }

    public function index(): Response|RedirectResponse
    {
        if (! tenant('id')) {
            abort(404);
        }

        return Inertia::render('Setup/Index', [
            'guide' => $this->setup->snapshot(),
            'welcome' => (bool) session('welcome'),
        ]);
    }

    public function completeStep(string $step): RedirectResponse
    {
        $this->setup->completeStep($step);

        return back();
    }

    public function finish(): RedirectResponse
    {
        $this->setup->finish();

        return redirect()->route('dashboard')->with('success', 'Setup complete. Your helpdesk is ready.');
    }
}
