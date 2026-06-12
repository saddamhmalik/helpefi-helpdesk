<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Mail\ResetPasswordMail;
use App\Domains\Channels\Services\OutboundMailService;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class PasswordResetMailService
{
    public function __construct(private OutboundMailService $outbound)
    {
    }

    public function send(User $user, string $token): void
    {
        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false));

        try {
            Mail::mailer($this->outbound->resolveMailerName())->to($user->email)->send(
                new ResetPasswordMail($user, $resetUrl),
            );
        } catch (TransportExceptionInterface) {
            throw ValidationException::withMessages([
                'email' => [__('passwords.mail_failed')],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'email' => [__('passwords.mail_failed')],
            ]);
        }
    }
}
