<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Brands\Models\Brand;
use App\Domains\Auth\Services\PortalAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalAuthController extends Controller
{
    public function __construct(private PortalAuthService $portalAuth)
    {
    }

    public function showLogin(): Response
    {
        return Inertia::render('Portal/Login');
    }

    public function login(Request $request, Brand $brand): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $this->portalAuth->attemptLogin($data['email'], $data['password'], $data['remember'] ?? false);

        return redirect()->intended(route('portal.my-tickets', ['brand' => $brand]));
    }

    public function showRegister(): Response
    {
        return Inertia::render('Portal/Register');
    }

    public function register(Request $request, Brand $brand): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->portalAuth->register($data['name'], $data['email'], $data['password']);

        $this->portalAuth->attemptLogin($data['email'], $data['password']);

        return redirect()->route('portal.my-tickets', ['brand' => $brand])->with('success', 'Welcome! Your account is ready.');
    }

    public function logout(Brand $brand): RedirectResponse
    {
        $this->portalAuth->logout();

        return redirect()->route('portal.index', ['brand' => $brand]);
    }
}
