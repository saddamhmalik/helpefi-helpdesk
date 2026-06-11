<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Services\PasswordResetService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetController extends Controller
{
    public function __construct(private PasswordResetService $passwordReset)
    {
    }

    public function showForgot(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $this->passwordReset->sendResetLink($request->input('email'));

        return back()->with('success', __('passwords.sent_notice'));
    }

    public function showReset(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $this->passwordReset->reset($data);

        return redirect()
            ->route('login')
            ->with('success', __('passwords.reset_notice'));
    }
}
