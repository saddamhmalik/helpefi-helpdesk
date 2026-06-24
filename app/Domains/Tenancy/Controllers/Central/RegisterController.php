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
use Throwable;

class RegisterController extends Controller
{
    public function __construct(
        private RegistrationVerificationService $verification,
        private TenantProvisioningService $provisioning,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Central/Register', [
            ...CentralMarketingPresenter::shared(),
            'verificationSent' => filter_var(session('verification_sent', false), FILTER_VALIDATE_BOOLEAN),
            'verificationEmail' => (string) session('verification_email', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->verification->registerRules());

        $this->verification->register($data);

        return redirect()
            ->route('central.register')
            ->with('verification_sent', true)
            ->with('verification_email', $data['email']);
    }

    public function resend(Request $request): RedirectResponse
    {
        $data = $request->validate($this->verification->resendRules());

        $this->verification->resend($data['email']);

        return redirect()
            ->route('central.register')
            ->with('verification_sent', true)
            ->with('verification_email', $data['email']);
    }

    public function verify(Request $request, string $token): HttpResponse|RedirectResponse
    {
        validator(['token' => $token], $this->verification->verificationTokenRules())->validate();

        try {
            $tenant = $this->verification->verify($token);
        } catch (InvalidRegistrationTokenException $exception) {
            return redirect()->route('central.register')->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('central.register')
                ->with('error', 'We could not finish creating your workspace. Please try signing up again or contact support.');
        }

        return Inertia::location($this->provisioning->welcomeUrl($tenant, $tenant->admin_email));
    }
}
