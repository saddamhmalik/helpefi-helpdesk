<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Repositories\PlatformUserRepository;
use App\Models\PlatformUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PlatformAuthService
{
    public function __construct(
        private PlatformUserRepository $users,
        private PlatformAuditLogService $audit,
    ) {
    }

    public function attempt(string $email, string $password, bool $remember = false): PlatformUser
    {
        $user = $this->users->findByEmail($email);

        if (! $user || ! $user->is_active) {
            $this->recordLoginFailed($email);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        if (! Auth::guard('platform')->attempt(['email' => $email, 'password' => $password], $remember)) {
            $this->recordLoginFailed($email);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $authenticated = Auth::guard('platform')->user();
        assert($authenticated instanceof PlatformUser);

        $this->audit->record(
            'platform.auth.login',
            $authenticated->id,
            $authenticated->email,
        );

        return $authenticated;
    }

    public function logout(): void
    {
        $user = Auth::guard('platform')->user();

        if ($user instanceof PlatformUser) {
            $this->audit->record(
                'platform.auth.logout',
                $user->id,
                $user->email,
            );
        }

        Auth::guard('platform')->logout();
    }

    public function recordLoginFailed(string $email): void
    {
        $this->audit->record(
            'platform.auth.login_failed',
            actorEmail: $email,
        );
    }
}
