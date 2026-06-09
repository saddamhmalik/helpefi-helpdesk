<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Platform\Services\PlatformTenantService;
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
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('q'));
        $status = (string) $request->string('status', 'all');

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
                    'price' => $plan['price'],
                ])
                ->values()
                ->all(),
            'stripe_enabled' => (bool) config('stripe.enabled'),
        ]);
    }

    public function update(Request $request, string $tenant): RedirectResponse
    {
        $slugs = implode(',', array_keys($this->plans->all()));

        $data = $request->validate([
            'is_blocked' => ['sometimes', 'boolean'],
            'plan' => ['sometimes', 'nullable', 'string', 'in:'.$slugs],
        ]);

        $this->tenants->update($tenant, $data);

        return back()->with('success', 'Workspace updated.');
    }
}
