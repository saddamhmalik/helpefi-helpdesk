<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Services\ProfileService;
use App\Domains\Auth\Services\UserPreferenceService;
use App\Domains\Security\Services\SecuritySettingService;
use App\Domains\Security\Services\TwoFactorService;
use App\Domains\Sla\Services\BusinessHoursService;
use App\Http\Controllers\Controller;
use App\Support\AppearanceSupport;
use App\Support\AvatarSupport;
use App\Support\LocaleSupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private ProfileService $profileService,
        private UserPreferenceService $preferences,
        private BusinessHoursService $businessHours,
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
            'locale' => $this->preferences->locale($user),
            'storedTimezone' => $user->timezone,
            'appearance' => $this->preferences->appearance($user),
            'avatarType' => AvatarSupport::resolveType($user->avatar_type),
            'avatarUrl' => AvatarSupport::url($user),
            'gravatarPreviewUrl' => AvatarSupport::gravatarUrl($user->email),
            'avatarOptions' => AvatarSupport::options(),
            'localeOptions' => LocaleSupport::options(),
            'timezoneOptions' => $this->businessHours->timezoneOptions(),
            'appearanceOptions' => AppearanceSupport::options(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if ($request->input('timezone') === '') {
            $request->merge(['timezone' => null]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'locale' => ['required', 'string', Rule::in(LocaleSupport::APP_LOCALES)],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'appearance' => ['required', 'string', Rule::in(AppearanceSupport::MODES)],
            'avatar_type' => ['nullable', 'string', Rule::in(AvatarSupport::TYPES)],
        ]);

        $this->profileService->update($request->user(), $data);

        return back()->with('success', __('messages.profile_updated'));
    }

    public function updateAppearance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'appearance' => ['required', 'string', Rule::in(AppearanceSupport::MODES)],
        ]);

        $this->profileService->updateAppearance($request->user(), $data['appearance']);

        return back();
    }

    public function updateLocale(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in(LocaleSupport::APP_LOCALES)],
        ]);

        $this->profileService->updateLocale($request->user(), $data['locale']);

        return back();
    }

    public function uploadAvatar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048', 'dimensions:max_width=2048,max_height=2048'],
        ]);

        $this->profileService->uploadAvatar($request->user(), $data['avatar']);

        return back()->with('success', __('messages.profile_updated'));
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $this->profileService->removeAvatar($request->user());

        return back()->with('success', __('messages.profile_updated'));
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

        return back()->with('success', __('messages.password_updated'));
    }
}
