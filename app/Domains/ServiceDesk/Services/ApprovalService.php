<?php

namespace App\Domains\ServiceDesk\Services;

use App\Domains\Billing\Contracts\FeatureEntitlementChecker;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\ServiceDesk\Events\TicketApprovalApproved;
use App\Domains\ServiceDesk\Events\TicketApprovalRejected;
use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\ServiceDesk\Models\ApprovalRequest;
use App\Domains\ServiceDesk\Models\ApprovalRequestStep;
use App\Domains\ServiceDesk\Repositories\ApprovalRequestRepository;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Domains\Tickets\Services\TicketStatusLookup;
use App\Domains\Security\Support\AuditRecorder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    public function __construct(
        private ApprovalRequestRepository $requests,
        private TicketRepository $tickets,
        private FeatureEntitlementChecker $entitlements,
        private NotificationService $notifications,
        private ApprovalMailer $mailer,
        private AuditRecorder $audit,
        private TicketStatusLookup $statusLookup,
    ) {
    }

    public function startFromCatalog(Ticket $ticket, ServiceCatalogItem $item, ?User $requestedBy = null): ApprovalRequest
    {
        $this->entitlements->assertFeature('service_desk');

        $approverIds = collect($item->approver_user_ids ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($approverIds === []) {
            throw ValidationException::withMessages([
                'approver_user_ids' => 'At least one approver is required when approval is enabled.',
            ]);
        }

        return $this->start($ticket, $approverIds, $item->id, $requestedBy);
    }

    public function evaluateForNewTicket(Ticket $ticket, ?int $requesterUserId = null): ?ApprovalRequest
    {
        if (! $this->entitlements->canUseFeature('service_desk')) {
            return null;
        }

        if ($this->requests->findForTicket($ticket->id)?->isPending()) {
            return null;
        }

        if ($ticket->service_catalog_item_id) {
            return null;
        }

        if ($ticket->type !== ServiceCatalogItem::TYPE_CHANGE) {
            return null;
        }

        $settings = $this->requests->settings();

        if (! $settings->change_requires_approval) {
            return null;
        }

        $approverIds = collect($settings->change_approver_user_ids ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($approverIds === []) {
            return null;
        }

        $requester = $requesterUserId ? User::query()->find($requesterUserId) : null;

        return $this->start($ticket, $approverIds, null, $requester);
    }

    public function settingsSnapshot(): array
    {
        $this->entitlements->assertFeature('service_desk');
        $settings = $this->requests->settings();

        return [
            'change_requires_approval' => (bool) $settings->change_requires_approval,
            'change_approver_user_ids' => $settings->change_approver_user_ids ?? [],
            'agents' => User::query()
                ->role(['admin', 'agent'])
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ];
    }

    public function updateSettings(array $data): array
    {
        $this->entitlements->assertFeature('service_desk');

        $approverIds = collect($data['change_approver_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (($data['change_requires_approval'] ?? false) && $approverIds === []) {
            throw ValidationException::withMessages([
                'change_approver_user_ids' => 'Select at least one approver for change requests.',
            ]);
        }

        $this->requests->updateSettings($this->requests->settings(), [
            'change_requires_approval' => (bool) ($data['change_requires_approval'] ?? false),
            'change_approver_user_ids' => ($data['change_requires_approval'] ?? false) ? $approverIds : [],
        ]);

        return $this->settingsSnapshot();
    }

    public function approve(int $requestId, int $userId, ?string $note = null): ApprovalRequest
    {
        $this->entitlements->assertFeature('service_desk');

        return DB::transaction(function () use ($requestId, $userId, $note) {
            $request = $this->requests->find($requestId);
            $step = $this->assertCurrentApprover($request, $userId);

            $this->requests->updateStep($step, [
                'status' => ApprovalRequestStep::STATUS_APPROVED,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);

            $nextStep = $request->steps()->where('step_order', $request->current_step + 1)->first();

            if ($nextStep) {
                $request = $this->requests->update($request, [
                    'current_step' => $request->current_step + 1,
                ]);

                $this->notifyCurrentApprover($request);

                $this->recordApprovalAudit(
                    'service_desk.approval_step_approved',
                    $request,
                    $userId,
                    [
                        'approval_request_id' => $request->id,
                        'approver_name' => User::query()->find($userId)?->name,
                        'next_approver_name' => $nextStep->approver?->name,
                    ],
                );

                return $this->requests->find($request->id);
            }

            $request = $this->finalize($request, ApprovalRequest::STATUS_APPROVED, $note);
            $ticket = $this->openApprovedTicket($request->ticket_id);

            TicketApprovalApproved::dispatch($ticket, $request);

            $this->notifications->approvalDecided($request, true);

            $this->recordApprovalAudit(
                'service_desk.approval_approved',
                $request,
                $userId,
                [
                    'approval_request_id' => $request->id,
                    'approver_name' => User::query()->find($userId)?->name,
                    'note' => $note,
                ],
            );

            return $this->requests->find($request->id);
        });
    }

    public function reject(int $requestId, int $userId, ?string $note = null): ApprovalRequest
    {
        $this->entitlements->assertFeature('service_desk');

        return DB::transaction(function () use ($requestId, $userId, $note) {
            $request = $this->requests->find($requestId);
            $step = $this->assertCurrentApprover($request, $userId);

            $this->requests->updateStep($step, [
                'status' => ApprovalRequestStep::STATUS_REJECTED,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);

            $request = $this->finalize($request, ApprovalRequest::STATUS_REJECTED, $note);
            $ticket = $this->closeRejectedTicket($request->ticket_id);

            TicketApprovalRejected::dispatch($ticket, $request);

            $this->notifications->approvalDecided($request, false);

            $this->recordApprovalAudit(
                'service_desk.approval_rejected',
                $request,
                $userId,
                [
                    'approval_request_id' => $request->id,
                    'approver_name' => User::query()->find($userId)?->name,
                    'note' => $note,
                ],
            );

            return $this->requests->find($request->id);
        });
    }

    public function snapshotForTicket(int $ticketId): ?array
    {
        if (! $this->entitlements->canUseFeature('service_desk')) {
            return null;
        }

        $request = $this->requests->findForTicket($ticketId);

        if (! $request) {
            return null;
        }

        return $this->toArray($request);
    }

    public function list(array $filters, int $perPage = 15)
    {
        $this->entitlements->assertFeature('service_desk');

        return $this->requests->paginate($filters, $perPage);
    }

    public function pendingCountForUser(int $userId): int
    {
        if (! $this->entitlements->canUseFeature('service_desk')) {
            return 0;
        }

        return $this->requests->pendingCounts($userId)['pending_mine'];
    }

    public function pendingCount(): int
    {
        if (! $this->entitlements->canUseFeature('service_desk')) {
            return 0;
        }

        return $this->pendingCounts(0)['pending'];
    }

    public function pendingCounts(int $userId): array
    {
        if (! $this->entitlements->canUseFeature('service_desk')) {
            return ['pending' => 0, 'pending_mine' => 0];
        }

        return $this->requests->pendingCounts($userId);
    }

    public function canUserDecide(ApprovalRequest $request, int $userId): bool
    {
        if (! $request->isPending()) {
            return false;
        }

        $step = $this->requests->currentStep($request);

        return $step
            && $step->status === ApprovalRequestStep::STATUS_PENDING
            && (int) $step->approver_user_id === $userId;
    }

    private function start(
        Ticket $ticket,
        array $approverIds,
        ?int $catalogItemId,
        ?User $requestedBy = null,
    ): ApprovalRequest {
        $pendingStatus = $this->tickets->statuses()->firstWhere('slug', 'pending')
            ?? $this->tickets->statuses()->firstWhere('slug', 'open');

        $this->tickets->update($ticket, [
            'ticket_status_id' => $pendingStatus->id,
            'closed_at' => null,
        ]);

        $request = $this->requests->create([
            'ticket_id' => $ticket->id,
            'service_catalog_item_id' => $catalogItemId,
            'subject' => $ticket->subject,
            'status' => ApprovalRequest::STATUS_PENDING,
            'current_step' => 1,
            'requested_by_user_id' => $requestedBy?->id,
            'requester_contact_id' => $ticket->contact_id,
        ], $approverIds);

        $this->audit->record('service_desk.approval_requested', $ticket, [
            'approval_request_id' => $request->id,
            'approver_names' => User::query()->whereIn('id', $approverIds)->orderBy('name')->pluck('name')->all(),
            'subject' => $ticket->subject,
        ], $requestedBy?->id);

        $this->notifyCurrentApprover($request);

        return $request;
    }

    private function assertCurrentApprover(ApprovalRequest $request, int $userId): ApprovalRequestStep
    {
        if (! $request->isPending()) {
            throw new AuthorizationException('This approval request is no longer pending.');
        }

        $step = $this->requests->currentStep($request);

        if (! $step || (int) $step->approver_user_id !== $userId) {
            throw new AuthorizationException('You are not the current approver for this request.');
        }

        return $step;
    }

    private function finalize(ApprovalRequest $request, string $status, ?string $note): ApprovalRequest
    {
        return $this->requests->update($request, [
            'status' => $status,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);
    }

    private function openApprovedTicket(int $ticketId): Ticket
    {
        $openStatus = $this->statusLookup->defaultOpen()
            ?? $this->tickets->statuses()->first();

        $ticket = $this->tickets->update($this->tickets->find($ticketId), [
            'ticket_status_id' => $openStatus->id,
            'closed_at' => null,
        ]);

        return $this->tickets->find($ticket->id);
    }

    private function closeRejectedTicket(int $ticketId): Ticket
    {
        $closedStatus = $this->statusLookup->defaultClosed();

        $ticket = $this->tickets->update($this->tickets->find($ticketId), [
            'ticket_status_id' => $closedStatus->id,
            'closed_at' => now(),
        ]);

        return $this->tickets->find($ticket->id);
    }

    private function notifyCurrentApprover(ApprovalRequest $request): void
    {
        $request = $this->requests->find($request->id);
        $step = $this->requests->currentStep($request);

        if (! $step?->approver) {
            return;
        }

        $reviewUrl = $this->mailer->signedReviewUrl($request, $step);
        $this->notifications->approvalPending($request, $step->approver, $reviewUrl);
    }

    private function recordApprovalAudit(string $event, ApprovalRequest $request, int $userId, array $properties): void
    {
        $this->audit->record(
            $event,
            Ticket::query()->findOrFail($request->ticket_id),
            $properties,
            $userId,
        );
    }

    private function toArray(ApprovalRequest $request): array
    {
        return [
            'id' => $request->id,
            'status' => $request->status,
            'current_step' => $request->current_step,
            'decided_at' => $request->decided_at?->toIso8601String(),
            'decision_note' => $request->decision_note,
            'steps' => $request->steps->map(fn (ApprovalRequestStep $step) => [
                'id' => $step->id,
                'step_order' => $step->step_order,
                'status' => $step->status,
                'decided_at' => $step->decided_at?->toIso8601String(),
                'decision_note' => $step->decision_note,
                'approver' => $step->approver ? [
                    'id' => $step->approver->id,
                    'name' => $step->approver->name,
                    'email' => $step->approver->email,
                ] : null,
            ])->values()->all(),
        ];
    }
}
