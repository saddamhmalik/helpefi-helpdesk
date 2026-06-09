<?php

namespace App\Domains\Security\Controllers;

use App\Domains\Auth\Services\AuthService;
use App\Domains\Security\Services\TwoFactorService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactor,
        private AuthService $auth,
    ) {
    }

    public function showChallenge(): Response|RedirectResponse
    {
        if (! $this->twoFactor->pendingUser()) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function verifyChallenge(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $this->twoFactor->completePendingLogin($request->string('code')->toString());

        return redirect()->intended($this->auth->homeRoute());
    }

    public function setup(Request $request): RedirectResponse
    {
        $setup = $this->twoFactor->beginSetup($request->user());

        return back()->with([
            'two_factor_setup' => $setup,
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $result = $this->twoFactor->confirmSetup($request->user(), $data['code']);

        $request->session()->put('two_factor_verified', true);

        return back()->with([
            'success' => 'Two-factor authentication enabled.',
            'recovery_codes' => $result['recovery_codes'],
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $this->twoFactor->disable($request->user(), $data['password']);
        $request->session()->forget('two_factor_verified');

        return back()->with('success', 'Two-factor authentication disabled.');
    }
}
