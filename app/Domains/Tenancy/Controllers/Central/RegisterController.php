<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Exceptions\InvalidRegistrationTokenException;
use App\Domains\Tenancy\Services\RegistrationVerificationService;
use App\Domains\Tenancy\Services\TenantProvisioningService;
use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class RegisterController extends Controller
{
    public function __construct(
        private RegistrationVerificationService $verification,
        private TenantProvisioningService $provisioning,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Central/Register', CentralMarketingPresenter::shared());
    }

    public function store(Request $request): Response
    {
        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->verification->register($data);

        return $this->verificationSentResponse($data['email']);
    }

    public function resend(Request $request): Response
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $this->verification->resend($data['email']);

        return $this->verificationSentResponse($data['email']);
    }

    public function verify(Request $request, string $token): HttpResponse|RedirectResponse
    {
        try {
            $tenant = $this->verification->verify($token);
        } catch (InvalidRegistrationTokenException $exception) {
            return redirect()->route('central.register')->with('error', $exception->getMessage());
        }

        return Inertia::location($this->provisioning->welcomeUrl($tenant, $tenant->admin_email));
    }

    private function verificationSentResponse(string $email): Response
    {
        return Inertia::render('Central/Register', [
            ...CentralMarketingPresenter::shared(),
            'verificationSent' => true,
            'verificationEmail' => $email,
        ]);
    }
}
