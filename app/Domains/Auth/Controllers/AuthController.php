<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Requests\LoginRequest;
use App\Domains\Auth\Requests\RegisterRequest;
use App\Domains\Auth\Services\AuthService;
use App\Domains\Security\Exceptions\TwoFactorRequiredException;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService, private \App\Domains\Security\Services\SsoService $sso)
    {
    }

    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login', [
            'sso' => $this->sso->loginOptions(),
        ]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        try {
            $this->authService->attemptLogin(
                $request->validated('email'),
                $request->validated('password'),
                $request->boolean('remember'),
            );
        } catch (TwoFactorRequiredException) {
            return redirect()->route('two-factor.challenge');
        }

        return redirect()->intended($this->authService->homeRoute());
    }

    public function showRegister(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $this->authService->register(
            $request->validated('name'),
            $request->validated('email'),
            $request->validated('password'),
        );

        $this->authService->attemptLogin(
            $request->validated('email'),
            $request->validated('password'),
        );

        return redirect($this->authService->homeRoute());
    }

    public function logout(): RedirectResponse
    {
        $this->authService->logout();

        return redirect()->route('login');
    }
}
