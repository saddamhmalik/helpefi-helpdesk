<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Models\Invitation;
use App\Domains\Auth\Repositories\InvitationRepository;
use App\Domains\Auth\Repositories\MemberRepository;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Security\Support\AuditRecorder;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public function __construct(
        private InvitationRepository $invitations,
        private MemberRepository $members,
        private BillingService $billing,
        private AuditRecorder $audit,
        private InvitationMailService $invitationMail,
    ) {
    }

    public function pending(): Collection
    {
        return $this->invitations->pending();
    }

    public function invite(int $inviterId, string $email, string $role, ?int $teamId = null): Invitation
    {
        $email = strtolower(trim($email));

        if ($this->members->findByEmail($email)) {
            throw ValidationException::withMessages([
                'email' => 'A user with this email already exists.',
            ]);
        }

        $existing = $this->invitations->pendingForEmail($email);

        if ($existing) {
            throw ValidationException::withMessages([
                'email' => 'An invitation is already pending for this email.',
            ]);
        }

        if (in_array($role, ['admin', 'agent'], true)) {
            $this->billing->assertLimit('agents', 1);
        }

        $invitation = $this->invitations->create([
            'email' => $email,
            'token' => Invitation::generateToken(),
            'role' => $role,
            'team_id' => $teamId,
            'invited_by' => $inviterId,
            'expires_at' => now()->addDays(7),
        ]);

        $this->audit->record('member.invited', null, [
            'email' => $email,
            'role' => $role,
        ], $inviterId);

        $this->invitationMail->queue($invitation);

        return $invitation;
    }

    public function findValid(string $token): Invitation
    {
        $invitation = $this->invitations->findByToken($token);

        if ($invitation->isAccepted()) {
            throw ValidationException::withMessages([
                'token' => 'This invitation has already been accepted.',
            ]);
        }

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages([
                'token' => 'This invitation has expired.',
            ]);
        }

        return $invitation;
    }

    public function accept(string $token, string $name, string $password): User
    {
        $invitation = $this->findValid($token);

        return $this->createUserFromInvitation($invitation, $name, $password);
    }

    public function acceptViaSso(Invitation $invitation, string $name, string $provider, string $subject): User
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'email' => 'This invitation is no longer valid.',
            ]);
        }

        if (! in_array($invitation->role, ['admin', 'agent'], true)) {
            throw ValidationException::withMessages([
                'email' => 'SSO login is only available for agent accounts.',
            ]);
        }

        $user = $this->createUserFromInvitation(
            $invitation,
            $name,
            Str::random(32),
        );

        $user->update([
            'sso_provider' => $provider,
            'sso_subject' => $subject,
        ]);

        return $user->fresh();
    }

    private function createUserFromInvitation(Invitation $invitation, string $name, string $password): User
    {
        if ($this->members->findByEmail($invitation->email)) {
            throw ValidationException::withMessages([
                'email' => 'A user with this email already exists.',
            ]);
        }

        if (in_array($invitation->role, ['admin', 'agent'], true)) {
            $this->billing->assertLimit('agents', 1);
        }

        $user = $this->members->createMember(
            $name,
            $invitation->email,
            $password,
            $invitation->role,
        );

        if ($invitation->team_id) {
            $this->members->attachToTeam($user, (int) $invitation->team_id);
        }

        $this->invitations->markAccepted($invitation);

        return $user;
    }

    public function acceptUrl(Invitation $invitation): string
    {
        return url('/invitations/'.$invitation->token);
    }

    public function shouldExposeAcceptUrl(): bool
    {
        if (app()->environment('production')) {
            return false;
        }

        if ($this->invitationMail->isDeliveryConfigured()) {
            return false;
        }

        return true;
    }
}
