<?php

namespace App\Domains\Auth\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private User $user,
        private string $resetUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset your '.config('app.name').' password',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.reset-password',
            with: [
                'userName' => $this->user->name,
                'resetUrl' => $this->resetUrl,
                'appName' => config('app.name'),
            ],
        );
    }
}
