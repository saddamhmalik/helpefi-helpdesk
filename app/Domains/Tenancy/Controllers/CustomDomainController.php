<?php

namespace App\Domains\Tenancy\Controllers;

use App\Domains\Billing\Services\BillingService;
use App\Domains\Tenancy\Services\TenantDomainService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomDomainController extends Controller
{
    public function __construct(private TenantDomainService $domains)
    {
    }

    public function index(BillingService $billing): Response
    {
        $snapshot = $billing->snapshot();

        return Inertia::render('Settings/CustomDomain', [
            'customDomain' => $this->domains->snapshot(),
            'billingPlan' => $snapshot['plan']['name'] ?? 'Current plan',
            'billingFeatures' => $snapshot['features'] ?? [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
        ]);

        $this->domains->requestCustomDomain($data['domain']);

        return back()->with('success', 'Custom domain added. Add the DNS records below, then verify.');
    }

    public function verify(): RedirectResponse
    {
        $this->domains->verifyCustomDomain();

        return back()->with('success', 'Custom domain verified and set as your primary workspace URL.');
    }

    public function destroy(): RedirectResponse
    {
        $this->domains->removeCustomDomain();

        return back()->with('success', 'Custom domain removed.');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'redirect_platform_domain' => ['required', 'boolean'],
        ]);

        $this->domains->updatePreferences((bool) $data['redirect_platform_domain']);

        return back()->with('success', 'Custom domain preferences updated.');
    }
}
