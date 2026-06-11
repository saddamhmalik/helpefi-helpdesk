<?php

namespace App\Domains\Auth\Services;

use App\Domains\Security\Services\AuditLogService;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    public function __construct(private AuditLogService $audit)
    {
    }

    public function sendResetLink(string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || $user->hasRole('customer')) {
            return;
        }

        $status = Password::sendResetLink(['email' => $email]);

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }

    public function reset(array $credentials): void
    {
        $status = Password::reset(
            $credentials,
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();
                $this->audit->record('auth.password_reset', $user->id, $user->email);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }
}
