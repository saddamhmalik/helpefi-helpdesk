<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Requests\LoginRequest;
use App\Domains\Auth\Services\AuthService;
use App\Domains\Security\Exceptions\TwoFactorRequiredException;
use App\Http\Controllers\Controller;
use App\Support\InertiaAuthRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService, private \App\Domains\Security\Services\SsoService $sso)
    {
    }

    public function showLogin(): Response
    {
        $this->authService->forgetCrossHostIntendedUrl();

        return Inertia::render('Auth/Login', [
            'sso' => $this->sso->loginOptions(),
        ]);
    }

    public function login(LoginRequest $request): HttpResponse|RedirectResponse
    {
        try {
            $this->authService->attemptLogin(
                $request->validated('email'),
                $request->validated('password'),
                $request->boolean('remember'),
            );
        } catch (TwoFactorRequiredException) {
            return InertiaAuthRedirect::to($request, route('two-factor.challenge'));
        }

        return InertiaAuthRedirect::to(
            $request,
            $this->authService->resolvePostLoginRedirect($this->authService->homeRoute()),
        );
    }

    public function logout(): RedirectResponse
    {
        $this->authService->logout();

        return redirect()->route('login');
    }
}
