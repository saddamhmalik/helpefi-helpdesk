<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Platform\Services\PlatformMailService;
use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Domains\Tenancy\Services\TenantProvisioningService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class RegisterController extends Controller
{
    public function __construct(
        private TenantProvisioningService $provisioning,
        private PlatformMailService $platformMail,
    ) {
    }

    public function create(): Response
    {
        return Inertia::render('Central/Register', CentralMarketingPresenter::shared());
    }

    public function store(Request $request): HttpResponse
    {
        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $tenant = $this->provisioning->provision(
            organizationName: $data['organization_name'],
            slug: $data['slug'],
            adminName: $data['name'],
            adminEmail: $data['email'],
            adminPassword: $data['password'],
        );

        $this->platformMail->sendRegistrationConfirmation($tenant, $data['name'], $data['email']);

        return Inertia::location($this->provisioning->welcomeUrl($tenant, $data['email']));
    }
}
