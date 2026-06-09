<?php

namespace App\Domains\Auth\Mail;

use App\Domains\Auth\Models\Invitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private Invitation $invitation,
        private User $inviter,
        private string $acceptUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been invited to '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.team-invitation',
            with: [
                'inviterName' => $this->inviter->name,
                'role' => ucfirst($this->invitation->role),
                'acceptUrl' => $this->acceptUrl,
                'expiresAt' => $this->invitation->expires_at,
                'appName' => config('app.name'),
            ],
        );
    }
}
