<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Services\TenantProvisioningService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function __construct(
        private TenantProvisioningService $provisioning,
    ) {
    }

    public function create(): Response
    {
        return Inertia::render('Central/Register', [
            'plans' => collect(config('plans', []))
                ->map(fn (array $plan, string $slug) => [
                    'slug' => $slug,
                    'name' => $plan['name'],
                    'price' => $plan['price'],
                ])
                ->values()
                ->all(),
            'defaultPlan' => config('billing.default_plan', 'professional'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'plan' => ['required', 'string', 'in:'.implode(',', array_keys(config('plans', [])))],
        ]);

        $tenant = $this->provisioning->provision(
            organizationName: $data['organization_name'],
            slug: $data['slug'],
            adminName: $data['name'],
            adminEmail: $data['email'],
            adminPassword: $data['password'],
            plan: $data['plan'],
        );

        tenancy()->initialize($tenant);

        $tenantBaseUrl = $this->provisioning->tenantUrl($tenant);
        \Illuminate\Support\Facades\URL::forceRootUrl($tenantBaseUrl);

        $welcomePath = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'welcome',
            now()->addMinutes(30),
            ['email' => $data['email']],
            absolute: false,
        );

        \Illuminate\Support\Facades\URL::forceRootUrl(config('app.url'));

        tenancy()->end();

        return redirect()->away($tenantBaseUrl.$welcomePath);
    }
}
