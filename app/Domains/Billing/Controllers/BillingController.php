<?php

namespace App\Domains\Billing\Controllers;

use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Services\BillingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
            'interval' => ['nullable', 'in:month,year'],
        ]);

        if ($this->billingService->usesStripeCheckout()) {
            throw ValidationException::withMessages([
                'plan' => 'Use the checkout button to change plans with Stripe.',
            ]);
        }

        $this->billingService->changePlan($data['plan'], $data['interval'] ?? 'month');

        return back()->with('success', 'Plan updated.');
    }

    public function checkout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'in:'.implode(',', $this->planRepository->slugs())],
            'interval' => ['nullable', 'in:month,year'],
        ]);

        $interval = $data['interval'] ?? 'month';

        $url = $this->billingService->initiatePlanChange(
            $data['plan'],
            (string) $request->user()->email,
            $request->getSchemeAndHttpHost().'/settings/billing?checkout=success',
            $request->getSchemeAndHttpHost().'/settings/billing?checkout=cancelled',
            $interval,
        );

        if (! is_string($url)) {
            return back()->with('success', 'Plan updated.');
        }

        return redirect()->away($url);
    }

    public function portal(Request $request): RedirectResponse
    {
        $url = $this->billingService->billingPortalUrl(
            (string) $request->user()->email,
            $request->getSchemeAndHttpHost().'/settings/billing',
        );

        return redirect()->away($url);
    }

    public function purchaseAddon(Request $request, string $addon): RedirectResponse
    {
        $onTrial = $this->billingService->snapshot()['on_trial'];

        $this->billingService->purchaseAddon($addon, (string) $request->user()->email);

        return back()->with('success', $onTrial
            ? 'Add-on enabled for your free trial.'
            : 'Add-on activated.');
    }

    public function cancelAddon(string $addon): RedirectResponse
    {
        $this->billingService->cancelAddon($addon);

        return back()->with('success', 'Add-on cancelled.');
    }
}
