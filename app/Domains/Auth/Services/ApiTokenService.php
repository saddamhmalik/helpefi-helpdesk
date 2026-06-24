<?php

namespace App\Domains\Auth\Services;

use App\Domains\Security\Services\AuditLogService;
use App\Domains\Security\Services\TwoFactorService;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Support\SecurityEventLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ApiTokenService
{
    public function __construct(
        private AuditLogService $audit,
        private TwoFactorService $twoFactor,
    ) {
    }

    public function createToken(User $user, string $name = 'api'): string
    {
        $plainToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plainToken);
        $ttlDays = max(1, (int) config('security.api_token_ttl_days', 90));

        PersonalAccessToken::query()
            ->where('user_id', $user->id)
            ->where('name', $name)
            ->delete();

        $user->forceFill(['api_token' => null])->save();

        PersonalAccessToken::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'token' => $hash,
            'abilities' => $this->abilitiesFor($user),
            'expires_at' => now()->addDays($ttlDays),
        ]);

        return $plainToken;
    }

    public function findUserByToken(string $token): ?User
    {
        $hash = hash('sha256', $token);

        $accessToken = PersonalAccessToken::query()
            ->where('token', $hash)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($accessToken) {
            $accessToken->forceFill(['last_used_at' => now()])->save();

            return $accessToken->user;
        }

        return null;
    }

    public function findTokenByPlainText(string $token): ?PersonalAccessToken
    {
        return PersonalAccessToken::query()
            ->where('token', hash('sha256', $token))
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    public function revokeToken(User $user): void
    {
        PersonalAccessToken::query()->where('user_id', $user->id)->delete();
        $user->forceFill(['api_token' => null])->save();
    }

    public function attemptLogin(string $email, string $password, ?string $totpCode = null): User
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            $this->audit->record('auth.login_failed', actorEmail: $email, properties: ['channel' => 'api']);
            SecurityEventLogger::authLoginFailed($email, 'api');

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();

        if ($user->hasRole('customer')) {
            Auth::logout();
            $this->audit->record('auth.login_failed', actorEmail: $email, properties: ['channel' => 'api', 'reason' => 'customer_portal']);
            SecurityEventLogger::authLoginFailed($email, 'api', 'customer_portal');

            throw ValidationException::withMessages([
                'email' => ['Please use the customer portal login endpoint.'],
            ]);
        }

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

    private function abilitiesFor(User $user): array
    {
        if ($user->hasRole('admin')) {
            return ['*'];
        }

        if ($user->hasRole('customer')) {
            return ['portal'];
        }

        return $user->getAllPermissions()->pluck('name')->all();
    }
}
