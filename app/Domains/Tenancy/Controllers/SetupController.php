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

    public function completeStep(Request $request, string $step): RedirectResponse
    {
        validator(['step' => $step], $this->setup->stepRules())->validate();

        if ($this->dummyData->isActive()) {
            return back()->withErrors([
                'setup' => 'Finish exploring sample data first. Remove it when you are ready to configure your workspace.',
            ]);
        }

        try {
            $this->setup->completeStep($step);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back();
    }

    public function finish(): RedirectResponse
    {
        if ($this->dummyData->isActive()) {
            return back()->withErrors([
                'setup' => 'Finish exploring sample data first. Remove it when you are ready to configure your workspace.',
            ]);
        }

        if ($this->dummyData->needsChoice()) {
            return back()->withErrors([
                'setup' => 'Choose whether to start with sample data or an empty workspace before finishing setup.',
            ]);
        }

        if ($this->dummyData->hasBootstrapDemo()) {
            return back()->withErrors([
                'setup' => 'Remove default demo content before finishing setup.',
            ]);
        }

        try {
            $this->setup->finish();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('dashboard')->with('success', 'Setup complete — your helpdesk is ready to go.');
    }
}
