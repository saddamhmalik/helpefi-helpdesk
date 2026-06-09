<?php

namespace App\Domains\Security\Services;

use App\Domains\Security\Support\Totp;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TwoFactorService
{
    public function __construct(
        private Totp $totp,
        private AuditLogService $audit,
    ) {
    }

    public function status(User $user): array
    {
        return [
            'enabled' => $user->hasTwoFactorEnabled(),
            'confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
        ];
    }

    public function beginSetup(User $user): array
    {
        $secret = $this->totp->generateSecret();

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return [
            'secret' => $secret,
            'otpauth_url' => $this->totp->provisioningUri($secret, $user->email),
        ];
    }

    public function confirmSetup(User $user, string $code): array
    {
        $secret = $this->decryptSecret($user);

        if (! $secret || ! $this->totp->verify($secret, $code)) {
            throw ValidationException::withMessages([
                'code' => 'The authentication code is invalid.',
            ]);
        }

        $plainCodes = $this->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode(
                collect($plainCodes)->map(fn (string $value) => Hash::make($value))->values()->all(),
            )),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->audit->record('auth.mfa_enabled', $user->id, $user->email);

        return [
            'recovery_codes' => $plainCodes,
        ];
    }

    public function disable(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'The password is incorrect.',
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->audit->record('auth.mfa_disabled', $user->id, $user->email);
    }

    public function verify(User $user, string $code): bool
    {
        $secret = $this->decryptSecret($user);

        if ($secret && $this->totp->verify($secret, $code)) {
            $this->audit->record('auth.mfa_verified', $user->id, $user->email);

            return true;
        }

        if ($this->consumeRecoveryCode($user, $code)) {
            $this->audit->record('auth.mfa_recovery_used', $user->id, $user->email);

            return true;
        }

        return false;
    }

    public function pendingUserId(): ?int
    {
        return session('login.id');
    }

    public function pendingUser(): ?User
    {
        $id = $this->pendingUserId();

        return $id ? User::query()->find($id) : null;
    }

    public function markPendingLogin(User $user, bool $remember): void
    {
        session([
            'login.id' => $user->id,
            'login.remember' => $remember,
        ]);
    }

    public function completePendingLogin(string $code): User
    {
        $user = $this->pendingUser();

        if (! $user) {
            throw ValidationException::withMessages([
                'code' => 'Your login session has expired.',
            ]);
        }

        if (! $this->verify($user, $code)) {
            throw ValidationException::withMessages([
                'code' => 'The authentication code is invalid.',
            ]);
        }

        $remember = (bool) session('login.remember', false);
        session()->forget(['login.id', 'login.remember']);
        session(['two_factor_verified' => true]);

        auth()->login($user, $remember);
        request()->session()->regenerate();

        return $user;
    }

    private function decryptSecret(User $user): ?string
    {
        if (! $user->two_factor_secret) {
            return null;
        }

        return Crypt::decryptString($user->two_factor_secret);
    }

    private function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn () => Str::upper(Str::random(4).'-'.Str::random(4)))
            ->all();
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        if (! $user->two_factor_recovery_codes) {
            return false;
        }

        $normalized = strtoupper(trim($code));
        $hashedCodes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true) ?: [];
        $remaining = [];

        foreach ($hashedCodes as $hashed) {
            if (Hash::check($normalized, $hashed)) {
                continue;
            }

            $remaining[] = $hashed;
        }

        if (count($remaining) === count($hashedCodes)) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode(array_values($remaining))),
        ])->save();

        return true;
    }
}
