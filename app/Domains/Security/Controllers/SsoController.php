<?php

namespace App\Domains\Security\Controllers;

use App\Domains\Auth\Services\AuthService;
use App\Domains\Security\Exceptions\TwoFactorRequiredException;
use App\Domains\Security\Services\OidcAuthService;
use App\Domains\Security\Services\SamlAuthService;
use App\Domains\Security\Services\SsoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class SsoController extends Controller
{
    public function __construct(
        private SsoService $sso,
        private OidcAuthService $oidc,
        private SamlAuthService $saml,
        private AuthService $auth,
    ) {
    }

    public function redirect(): RedirectResponse
    {
        return redirect()->away($this->sso->redirectUrl());
    }

    public function callback(): RedirectResponse
    {
        try {
            $identity = $this->oidc->handleCallback();
            $this->sso->completeLogin($identity);
        } catch (TwoFactorRequiredException) {
            return redirect()->route('two-factor.challenge');
        }

        return redirect()->intended($this->auth->homeRoute());
    }

    public function acs(Request $request): RedirectResponse
    {
        try {
            $identity = $this->saml->handleAcs($request->all());
            $this->sso->completeLogin($identity);
        } catch (TwoFactorRequiredException) {
            return redirect()->route('two-factor.challenge');
        }

        return redirect()->intended($this->auth->homeRoute());
    }

    public function metadata(): Response
    {
        return response($this->saml->metadata(), 200, ['Content-Type' => 'text/xml']);
    }

    public function slo(): RedirectResponse
    {
        return redirect()->route('login');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sso_enabled' => ['required', 'boolean'],
            'sso_protocol' => ['required', Rule::in(['oidc', 'saml'])],
            'sso_config' => ['nullable', 'array'],
        ]);

        $this->sso->update($data);

        return back()->with('success', 'SSO settings saved.');
    }
}
