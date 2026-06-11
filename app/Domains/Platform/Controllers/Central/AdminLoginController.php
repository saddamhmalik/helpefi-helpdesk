<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Services\PlatformAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminLoginController extends Controller
{
    public function __construct(private PlatformAuthService $auth)
    {
    }

    public function create(): Response
    {
        return Inertia::render('Central/Admin/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $this->auth->attempt(
            $credentials['email'],
            $credentials['password'],
            $request->boolean('remember'),
        );

        return redirect()->route('central.admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->auth->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('central.home');
    }
}
