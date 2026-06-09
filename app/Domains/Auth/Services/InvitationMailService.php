<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Jobs\SendTeamInvitationJob;
use App\Domains\Auth\Mail\TeamInvitationMail;
use App\Domains\Auth\Models\Invitation;
use App\Domains\Auth\Repositories\InvitationRepository;
use App\Domains\Channels\Repositories\MailSettingRepository;
use App\Domains\Channels\Services\OutboundMailService;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class InvitationMailService
{
    public function __construct(
        private InvitationRepository $invitations,
        private OutboundMailService $outbound,
        private MailSettingRepository $mailSettings,
    ) {
    }

    public function queue(Invitation $invitation): void
    {
        SendTeamInvitationJob::dispatch($invitation->id);
    }

    public function deliver(int $invitationId): void
    {
        $invitation = $this->invitations->findById($invitationId)->loadMissing('inviter');

        if (! $invitation->inviter) {
            throw new InvalidArgumentException('Invitation inviter not found.');
        }

        if (! $invitation->isPending()) {
            return;
        }

        $acceptUrl = url('/invitations/'.$invitation->token);
        $mailer = $this->resolveMailer();

        try {
            Mail::mailer($mailer)->to($invitation->email)->send(
                new TeamInvitationMail($invitation, $invitation->inviter, $acceptUrl),
            );
        } catch (TransportExceptionInterface $exception) {
            throw new InvalidArgumentException('Failed to send invitation email: '.$exception->getMessage());
        }
    }

    private function resolveMailer(): string
    {
        $this->outbound->applyGlobalConfig();

        $setting = $this->mailSettings->current();

        if ($setting->enabled) {
            return OutboundMailService::MAILER;
        }

        return config('mail.default');
    }
}
