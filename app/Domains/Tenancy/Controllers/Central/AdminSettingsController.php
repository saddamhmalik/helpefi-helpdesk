<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Domains\Tenancy\Support\CurrencyCatalog;
use App\Domains\Tenancy\Support\PlanCatalogDefinition;
use App\Domains\Tenancy\Services\CentralSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingsController extends Controller
{
    public function __construct(private CentralSettingsService $settings)
    {
    }

    public function edit(): Response
    {
        return Inertia::render('Central/Admin/Settings', [
            ...CentralMarketingPresenter::shared(),
            'settings' => $this->settings->snapshot(),
            'availableCurrencies' => CurrencyCatalog::forSelect(),
            'planCatalog' => PlanCatalogDefinition::forAdminUi(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $slugs = implode(',', PlanCatalogDefinition::slugs());
        $features = implode(',', PlanCatalogDefinition::featureKeys());

        $rules = [
            'trial_days' => ['required', 'integer', 'min:1', 'max:365'],
            'currency' => ['required', 'string', 'size:3', 'in:'.implode(',', CurrencyCatalog::codes())],
            'plans' => ['required', 'array', 'min:1'],
            'plans.*.slug' => ['required', 'string', 'in:'.$slugs],
            'plans.*.name' => ['required', 'string', 'max:100'],
            'plans.*.price' => ['required', 'integer', 'min:0', 'max:99999'],
            'plans.*.stripe_price_id' => ['nullable', 'string', 'max:255'],
            'plans.*.features' => ['array'],
            'plans.*.features.*' => ['string', 'in:'.$features],
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
}
