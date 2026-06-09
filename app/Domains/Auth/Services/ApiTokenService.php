<?php

namespace App\Domains\Auth\Services;

use App\Domains\Security\Services\AuditLogService;
use App\Domains\Security\Services\TwoFactorService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ApiTokenService
{
    public function __construct(
        private AuditLogService $audit,
        private TwoFactorService $twoFactor,
    ) {
    }

    public function createToken(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        $user->forceFill(['api_token' => hash('sha256', $token)])->save();

        return $token;
    }

    public function findUserByToken(string $token): ?User
    {
        return User::query()->where('api_token', hash('sha256', $token))->first();
    }

    public function revokeToken(User $user): void
    {
        $user->forceFill(['api_token' => null])->save();
    }

    public function attemptLogin(string $email, string $password, ?string $totpCode = null): User
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            $this->audit->record('auth.login_failed', actorEmail: $email, properties: ['channel' => 'api']);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();

        if ($user->hasTwoFactorEnabled()) {
            Auth::logout();

            if (! $totpCode || ! $this->twoFactor->verify($user, $totpCode)) {
                throw ValidationException::withMessages([
                    'totp_code' => ['Two-factor authentication code is required.'],
                ]);
            }
        }

        $this->audit->record('auth.login', $user->id, $user->email, properties: ['channel' => 'api']);

        return $user;
    }
}
