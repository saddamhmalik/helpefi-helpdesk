<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Platform\Services\PlatformTenantService;
use App\Domains\Tenancy\Services\CentralSettingsService;
use App\Domains\Tenancy\Services\TenantProvisioningService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTenantController extends Controller
{
    public function __construct(
        private PlatformTenantService $tenants,
        private PlanRepository $plans,
        private TenantProvisioningService $provisioning,
        private CentralSettingsService $settings,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('q'));
        $status = (string) $request->string('status', 'all');

        $healedSubscriptions = $this->provisioning->healMissingSubscriptions();
        $healedDomains = $this->provisioning->healMissingDomains();

        if ($healedSubscriptions > 0) {
            session()->flash(
                'success',
                "Started free trial for {$healedSubscriptions} workspace(s) that were missing a subscription.",
            );
        }

        if ($healedDomains > 0) {
            session()->flash(
                'success',
                "Restored platform domain for {$healedDomains} workspace(s) that were missing a domain.",
            );
        }

        return Inertia::render('Central/Admin/Tenants/Index', [
            'tenants' => $this->tenants->list(
                (int) $request->integer('per_page', 15),
                $search !== '' ? $search : null,
                $status,
            ),
            'stats' => $this->tenants->stats(),
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'plans' => collect($this->plans->all())
                ->map(fn (array $plan, string $slug) => [
                    'slug' => $slug,
                    'name' => $plan['name'],
                    'custom_pricing' => $plan['custom_pricing'] ?? false,
                    'price' => $plan['price'],
                    'price_monthly' => $plan['price_monthly'] ?? $plan['price'],
                    'price_yearly' => $plan['price_yearly'] ?? null,
                ])
                ->values()
                ->all(),
            'currency' => $this->settings->currencyMeta(),
            'razorpay_enabled' => (bool) config('razorpay.configured'),
        ]);
    }

    public function update(Request $request, string $tenant): RedirectResponse
    {
        $slugs = implode(',', array_keys($this->plans->all()));

        $data = $request->validate([
            'is_blocked' => ['sometimes', 'boolean'],
            'byo_allowed' => ['sometimes', 'boolean'],
            'plan' => ['sometimes', 'nullable', 'required_with:billing_interval,renews_at,note,custom_price', 'string', 'in:'.$slugs],
            'billing_interval' => ['sometimes', 'string', 'in:month,year'],
            'renews_at' => ['sometimes', 'nullable', 'date', 'after:today'],
            'custom_price' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999999'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
            'start_trial' => ['sometimes', 'boolean'],
        ]);

        if ($request->boolean('start_trial')) {
            $this->provisioning->ensureCentralSubscription($this->tenants->find($tenant));
        }

        $this->tenants->update($tenant, $data);

        return back()->with('success', 'Workspace updated.');
    }

    public function destroy(Request $request, string $tenant): RedirectResponse
    {
        $data = $request->validate([
            'confirm_slug' => ['required', 'string'],
        ]);

        $record = $this->tenants->find($tenant);

        if ($data['confirm_slug'] !== $record->slug) {
            return back()->withErrors([
                'confirm_slug' => 'Workspace slug does not match. Deletion cancelled.',
            ]);
        }

        $this->tenants->delete($tenant);

        return redirect()
            ->route('central.admin.tenants.index')
            ->with('success', 'Workspace and tenant database deleted.');
    }
}
