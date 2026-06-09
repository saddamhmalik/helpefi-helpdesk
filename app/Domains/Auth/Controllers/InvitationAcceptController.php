<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Services\AuthService;
use App\Domains\Auth\Services\InvitationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InvitationAcceptController extends Controller
{
    public function __construct(
        private InvitationService $invitationService,
        private AuthService $authService,
    ) {
    }

    public function show(string $token): Response|RedirectResponse
    {
        try {
            $invitation = $this->invitationService->findValid($token);
        } catch (\Illuminate\Validation\ValidationException) {
            return redirect()->route('login')->with('error', 'This invitation is invalid or expired.');
        }

        return Inertia::render('Auth/AcceptInvitation', [
            'invitation' => [
                'email' => $invitation->email,
                'role' => $invitation->role,
                'token' => $invitation->token,
            ],
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $this->invitationService->accept($token, $data['name'], $data['password']);

        $this->authService->attemptLogin($user->email, $data['password']);

        return redirect($this->authService->homeRoute())->with('success', 'Welcome to Helpdesk.');
    }
}
