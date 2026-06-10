<?php

namespace App\Domains\Auth\Mail;

use App\Domains\Auth\Models\Invitation;
use App\Domains\Channels\Models\EmailTemplate;
use App\Domains\Channels\Services\EmailTemplateService;
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
        $fallback = 'You have been invited to '.config('app.name');

        return new Envelope(
            subject: app(EmailTemplateService::class)->renderSubject(
                EmailTemplate::SLUG_TEAM_INVITATION,
                $this->templateVariables(),
                $fallback,
            ),
        );
    }

    public function content(): Content
    {
        $rendered = app(EmailTemplateService::class)->render(
            EmailTemplate::SLUG_TEAM_INVITATION,
            $this->templateVariables(),
        );

        if ($rendered !== null) {
            return new Content(
                htmlString: app(EmailTemplateService::class)->wrapHtml($rendered['body_html']),
            );
        }

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

    private function templateVariables(): array
    {
        return [
            'app_name' => config('app.name'),
            'inviter_name' => $this->inviter->name,
            'role' => ucfirst($this->invitation->role),
            'accept_url' => $this->acceptUrl,
            'expires_at' => $this->invitation->expires_at?->format('F j, Y g:i A T') ?? '',
        ];
    }
}
