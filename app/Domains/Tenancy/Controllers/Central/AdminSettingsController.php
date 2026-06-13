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
    ) {}

    public function general(): Response
    {
        return Inertia::render('Central/Admin/Settings/General', $this->sharedProps());
    }

    public function billing(): Response
    {
        return Inertia::render('Central/Admin/Settings/Billing', [
            ...$this->sharedProps(),
            'availableCurrencies' => CurrencyCatalog::forSelect(),
        ]);
    }

    public function plans(): Response
    {
        return Inertia::render('Central/Admin/Settings/Plans', [
            ...$this->sharedProps(),
            'planCatalog' => PlanCatalogDefinition::forAdminUi(),
            'defaultSlugs' => PlanCatalogDefinition::slugs(),
        ]);
    }

    public function addons(): Response
    {
        return Inertia::render('Central/Admin/Settings/Addons', [
            ...$this->sharedProps(),
            'addonCatalog' => AddonCatalogDefinition::forAdminUi(),
        ]);
    }

    public function branding(): Response
    {
        return Inertia::render('Central/Admin/Settings/Branding', $this->sharedProps());
    }

    public function update(Request $request): RedirectResponse
    {
        $features = implode(',', PlanCatalogDefinition::featureKeys());
        $addonKeys = implode(',', AddonCatalogDefinition::keys());

        $rules = [
            'trial_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'tenant_purge_grace_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'tenant_purge_enabled' => ['sometimes', 'boolean'],
            'currency' => ['sometimes', 'string', 'size:3', 'in:'.implode(',', CurrencyCatalog::codes())],
            'india_pricing' => ['sometimes', 'boolean'],
            'social_links' => ['sometimes', 'nullable', 'array'],
            'social_links.*' => ['nullable', 'url:http,https', 'max:255'],
            'plans' => ['sometimes', 'array', 'min:1', 'max:12'],
            'plans.*.slug' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'plans.*.name' => ['required', 'string', 'max:100'],
            'plans.*.price' => ['required', 'integer', 'min:0', 'max:99999'],
            'plans.*.price_yearly' => ['required', 'integer', 'min:0', 'max:999999'],
            'plans.*.price_india' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'plans.*.price_yearly_india' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'plans.*.razorpay_plan_id' => ['nullable', 'string', 'max:255'],
            'plans.*.razorpay_plan_id_yearly' => ['nullable', 'string', 'max:255'],
            'plans.*.razorpay_plan_id_monthly_india' => ['nullable', 'string', 'max:255'],
            'plans.*.razorpay_plan_id_yearly_india' => ['nullable', 'string', 'max:255'],
            'plans.*.features' => ['array'],
            'plans.*.features.*' => ['string', 'in:'.$features],
            'addons' => ['sometimes', 'nullable', 'array'],
            'addons.*.key' => ['required', 'string', 'in:'.$addonKeys],
            'addons.*.name' => ['required', 'string', 'max:100'],
            'addons.*.description' => ['nullable', 'string', 'max:500'],
            'addons.*.price_monthly' => ['required', 'integer', 'min:0', 'max:99999'],
            'addons.*.enabled' => ['required', 'boolean'],
            'addons.*.razorpay_plan_id_monthly' => ['nullable', 'string', 'max:255'],
        ];

        foreach (PlanCatalogDefinition::limitDefinitions() as $key => $definition) {
            $rules["plans.*.limits.{$key}"] = [
                'nullable',
                'integer',
                'min:'.(int) ($definition['min'] ?? 1),
                'max:'.(int) ($definition['max'] ?? 999999),
            ];
        }

        $data = $request->validate($rules, [
            'plans.*.slug.regex' => 'The plan identifier may only contain lowercase letters, numbers, and underscores, and must start with a letter.',
            'plans.*.slug.distinct' => 'Plan identifiers must be unique.',
        ]);

        $this->settings->update($data);

        $skipped = $this->settings->razorpaySyncWarnings();

        if ($skipped !== []) {
            return back()->with(
                'warning',
                'Settings saved, but Razorpay rejected '.implode(', ', $skipped)
                    .'. Those tiers are not purchasable until the currency is supported on your Razorpay account.',
            );
        }

        $message = config('razorpay.enabled')
            ? 'Platform settings updated and synced with Razorpay.'
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

    private function sharedProps(): array
    {
        return [
            ...CentralMarketingPresenter::shared(),
            'settings' => $this->settings->snapshot(),
        ];
    }
}
