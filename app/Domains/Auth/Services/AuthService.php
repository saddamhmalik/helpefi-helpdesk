<?php

namespace App\Domains\Auth\Services;

use App\Domains\Security\Exceptions\TwoFactorRequiredException;
use App\Domains\Security\Services\AuditLogService;
use App\Domains\Security\Services\TwoFactorService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private AuditLogService $audit,
        private TwoFactorService $twoFactor,
    ) {
    }

    public function attemptLogin(string $email, string $password, bool $remember = false): User
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            $this->audit->record('auth.login_failed', actorEmail: $email);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        if ($user->hasRole('customer')) {
            Auth::logout();
            $this->audit->record('auth.login_failed', actorEmail: $email, properties: ['reason' => 'customer_portal']);

            throw ValidationException::withMessages([
                'email' => 'Please use the customer login on the help center.',
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            Auth::logout();
            $this->twoFactor->markPendingLogin($user, $remember);

            throw new TwoFactorRequiredException;
        }

        request()->session()->regenerate();
        request()->session()->put('two_factor_verified', true);
        $this->audit->record('auth.login', $user->id, $user->email);

        return $user;
    }

    public function homeRoute(): string
    {
        $user = Auth::user();

        if ($user?->hasRole('admin')) {
            return route('admin.hub');
        }

        return route('dashboard');
    }

    public function register(string $name, string $email, string $password): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $user->assignRole(\Spatie\Permission\Models\Role::findOrCreate('agent'));

        return $user;
    }

    public function logout(): void
    {
        if ($user = Auth::user()) {
            $this->audit->record('auth.logout', $user->id, $user->email);
        }

        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
