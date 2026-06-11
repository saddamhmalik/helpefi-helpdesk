<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Mail\ResetPasswordMail;
use App\Domains\Channels\Repositories\MailSettingRepository;
use App\Domains\Channels\Services\OutboundMailService;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetMailService
{
    public function __construct(
        private OutboundMailService $outbound,
        private MailSettingRepository $mailSettings,
    ) {
    }

    public function send(User $user, string $token): void
    {
        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false));

        $mailer = $this->resolveMailer();

        try {
            Mail::mailer($mailer)->to($user->email)->send(
                new ResetPasswordMail($user, $resetUrl),
            );
        } catch (TransportExceptionInterface $exception) {
            throw ValidationException::withMessages([
                'email' => [__('passwords.mail_failed')],
            ]);
        }
    }

    private function resolveMailer(): string
    {
        $this->outbound->applyGlobalConfig();

        if ($this->mailSettings->current()->enabled) {
            return OutboundMailService::MAILER;
        }

        return config('mail.default');
    }
}
