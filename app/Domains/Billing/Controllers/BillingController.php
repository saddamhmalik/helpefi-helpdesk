<?php

namespace App\Domains\Billing\Controllers;

use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Services\BillingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(
        private BillingService $billingService,
        private PlanRepository $planRepository,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Settings/Billing', [
            'billing' => $this->billingService->snapshot(),
        ]);
    }

    public function updatePlan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'in:'.implode(',', $this->planRepository->slugs())],
        ]);

        $this->billingService->changePlan($data['plan']);

        return back()->with('success', 'Plan updated.');
    }
}
