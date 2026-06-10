<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Platform\Services\TenantPurgeService;
use App\Domains\Tenancy\Services\CentralSettingsService;
use App\Domains\Tenancy\Support\AddonCatalogDefinition;
use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Domains\Tenancy\Support\CurrencyCatalog;
use App\Domains\Tenancy\Support\PlanCatalogDefinition;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingsController extends Controller
{
    public function __construct(
        private CentralSettingsService $settings,
        private TenantPurgeService $tenantPurge,
    ) {
    }

    public function edit(): Response
    {
        return Inertia::render('Central/Admin/Settings', [
            ...CentralMarketingPresenter::shared(),
            'settings' => $this->settings->snapshot(),
            'availableCurrencies' => CurrencyCatalog::forSelect(),
            'planCatalog' => PlanCatalogDefinition::forAdminUi(),
            'addonCatalog' => AddonCatalogDefinition::forAdminUi(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $slugs = implode(',', PlanCatalogDefinition::slugs());
        $features = implode(',', PlanCatalogDefinition::featureKeys());
        $addonKeys = implode(',', AddonCatalogDefinition::keys());

        $rules = [
            'trial_days' => ['required', 'integer', 'min:1', 'max:365'],
            'tenant_purge_grace_days' => ['required', 'integer', 'min:1', 'max:365'],
            'tenant_purge_enabled' => ['required', 'boolean'],
            'currency' => ['required', 'string', 'size:3', 'in:'.implode(',', CurrencyCatalog::codes())],
            'plans' => ['required', 'array', 'min:1'],
            'plans.*.slug' => ['required', 'string', 'in:'.$slugs],
            'plans.*.name' => ['required', 'string', 'max:100'],
            'plans.*.price' => ['required', 'integer', 'min:0', 'max:99999'],
            'plans.*.price_yearly' => ['required', 'integer', 'min:0', 'max:999999'],
            'plans.*.stripe_price_id' => ['nullable', 'string', 'max:255'],
            'plans.*.stripe_price_id_yearly' => ['nullable', 'string', 'max:255'],
            'plans.*.features' => ['array'],
            'plans.*.features.*' => ['string', 'in:'.$features],
            'addons' => ['nullable', 'array'],
            'addons.*.key' => ['required', 'string', 'in:'.$addonKeys],
            'addons.*.name' => ['required', 'string', 'max:100'],
            'addons.*.description' => ['nullable', 'string', 'max:500'],
            'addons.*.price_monthly' => ['required', 'integer', 'min:0', 'max:99999'],
            'addons.*.enabled' => ['required', 'boolean'],
            'addons.*.stripe_price_id_monthly' => ['nullable', 'string', 'max:255'],
        ];

        foreach (PlanCatalogDefinition::limitDefinitions() as $key => $definition) {
            $rules["plans.*.limits.{$key}"] = [
                'nullable',
                'integer',
                'min:'.(int) ($definition['min'] ?? 1),
                'max:'.(int) ($definition['max'] ?? 999999),
            ];
        }

        $data = $request->validate($rules);

        $this->settings->update($data);

        $message = config('stripe.enabled')
            ? 'Platform settings updated and synced with Stripe.'
            : 'Platform settings updated.';

        return back()->with('success', $message);
    }

    public function purgeExpiredTenants(): RedirectResponse
    {
        $purged = $this->tenantPurge->purgeExpired();

        $count = count($purged);

        return back()->with(
            'success',
            $count > 0
                ? "Purged {$count} expired workspace(s) and dropped their databases."
                : 'No expired workspaces were eligible for purge.',
        );
    }
}
