<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Jobs\SendTeamInvitationJob;
use App\Domains\Auth\Mail\TeamInvitationMail;
use App\Domains\Auth\Models\Invitation;
use App\Domains\Auth\Repositories\InvitationRepository;
use App\Domains\Channels\Services\OutboundMailService;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class InvitationMailService
{
    public function __construct(
        private InvitationRepository $invitations,
        private OutboundMailService $outbound,
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

        try {
            Mail::mailer($this->outbound->resolveMailerName())->to($invitation->email)->send(
                new TeamInvitationMail($invitation, $invitation->inviter, $acceptUrl),
            );
        } catch (TransportExceptionInterface $exception) {
            throw new InvalidArgumentException('Failed to send invitation email: '.$exception->getMessage());
        }
    }

    public function isDeliveryConfigured(): bool
    {
        $mailer = $this->outbound->resolveMailerName();

        if ($mailer === OutboundMailService::MAILER) {
            return true;
        }

        return ! in_array($mailer, ['log', 'array'], true);
    }
}
