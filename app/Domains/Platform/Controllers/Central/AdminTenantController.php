<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Platform\Services\PlatformTenantReminderService;
use App\Domains\Platform\Services\PlatformTenantService;
use App\Domains\Tenancy\Services\CentralSettingsService;
use App\Domains\Tenancy\Services\TenantProvisioningService;
use App\Domains\Tenancy\Support\AddonCatalogDefinition;
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
        private PlatformTenantReminderService $reminders,
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
                    'features' => $plan['features'] ?? [],
                    'price' => $plan['price'],
                    'price_monthly' => $plan['price_monthly'] ?? $plan['price'],
                    'price_yearly' => $plan['price_yearly'] ?? null,
                    'price_monthly_india' => $plan['price_monthly_india'] ?? 0,
                    'price_yearly_india' => $plan['price_yearly_india'] ?? 0,
                ])
                ->values()
                ->all(),
            'addons' => collect($this->settings->addonCatalog())
                ->filter(fn (array $addon) => $addon['enabled'] ?? true)
                ->map(fn (array $addon, string $key) => [
                    'key' => $key,
                    'name' => $addon['name'],
                    'feature' => $addon['feature'],
                    'description' => $addon['description'],
                    'price_monthly' => $addon['price_monthly'],
                    'price_monthly_india' => $addon['price_monthly_india'] ?? 0,
                ])
                ->values()
                ->all(),
            'currency' => $this->settings->currencyMeta(),
            'india_pricing' => $this->settings->indiaPricingEnabled(),
            'india_currency' => $this->settings->indiaCurrencyMeta(),
            'razorpay_enabled' => (bool) config('razorpay.configured'),
        ]);
    }

    public function update(Request $request, string $tenant): RedirectResponse
    {
        $slugs = implode(',', array_keys($this->plans->all()));
        $addonKeys = implode(',', AddonCatalogDefinition::keys());
        $allowedCurrencies = [$this->settings->currency()];

        if ($this->settings->indiaPricingEnabled()) {
            $allowedCurrencies[] = $this->settings->indiaCurrency();
        }

        $data = $request->validate([
            'is_blocked' => ['sometimes', 'boolean'],
            'byo_allowed' => ['sometimes', 'boolean'],
            'plan' => ['sometimes', 'nullable', 'required_with:billing_interval,renews_at,note,custom_price,addons,billing_currency', 'string', 'in:'.$slugs],
            'billing_interval' => ['sometimes', 'string', 'in:month,year'],
            'billing_currency' => ['sometimes', 'nullable', 'string', 'size:3', 'in:'.implode(',', $allowedCurrencies)],
            'renews_at' => ['sometimes', 'nullable', 'date', 'after:today'],
            'custom_price' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999999'],
            'addons' => ['sometimes', 'nullable', 'array'],
            'addons.*' => ['string', 'in:'.$addonKeys],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
            'start_trial' => ['sometimes', 'boolean'],
        ]);

        if ($request->boolean('start_trial')) {
            $this->provisioning->ensureCentralSubscription($this->tenants->find($tenant));
        }

        $this->tenants->update($tenant, $data);

        return back()->with('success', 'Workspace updated.');
    }

    public function resendLifecycleEmail(Request $request, string $tenant): RedirectResponse
    {
        $data = $request->validate([
            'template_slug' => ['required', 'string', 'max:80'],
        ]);

        $this->reminders->resend($this->tenants->find($tenant), $data['template_slug']);

        return back()->with('success', 'Lifecycle email resent.');
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
