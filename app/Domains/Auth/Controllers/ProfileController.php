<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Services\ProfileService;
use App\Domains\Security\Services\SecuritySettingService;
use App\Domains\Security\Services\TwoFactorService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private ProfileService $profileService,
        private TwoFactorService $twoFactor,
        private SecuritySettingService $security,
    ) {
    }

    public function edit(): Response
    {
        $user = auth()->user();

        return Inertia::render('Settings/Profile', [
            'twoFactor' => $this->twoFactor->status($user),
            'mfaRequired' => $this->security->userMustEnrollMfa($user),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $this->profileService->update($request->user(), $data);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $this->profileService->updatePassword(
            $request->user(),
            $data['current_password'],
            $data['password'],
        );

        return back()->with('success', 'Password updated.');
    }
}
