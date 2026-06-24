<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Settings\Services\HelpdeskSettingService;
use App\Domains\Sla\Services\SlaService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketAttachment;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

class TicketService
{
    public function __construct(
        private TicketRepository $tickets,
        private SlaService $sla,
        private HelpdeskSettingService $helpdeskSettings,
        private TicketReadService $ticketReads,
        private TicketCreationService $creation,
        private TicketUpdateService $updates,
        private TicketReplyService $replies,
        private TicketMergeService $merges,
        private TicketSplitService $splits,
        private TicketWatcherService $watchers,
    ) {
    }

    public function fieldDefinitions(): array
    {
        return $this->helpdeskSettings->ticketFieldDefinitions();
    }

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return $this->tickets->paginate($perPage);
    }

    public function listFiltered(array $filters, int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->ticketReads->attachUnreadCounts(
            $this->tickets->paginateFiltered($filters, $perPage, $userId),
            $userId,
        );
    }

    public function show(int $id, ?User $viewer = null): Ticket
    {
        $viewer ??= auth()->user();
        $includeInternal = $viewer === null || ! $viewer->hasRole('customer');

        $ticket = $this->tickets->find($id, includeInternal: $includeInternal);

        if (! $ticket->slaTimer) {
            $this->sla->applyToTicket($ticket);
            $ticket->load([
                'slaTimer.policy.businessHours',
            ]);
        }

        return $ticket;
    }

    public function create(array $data, ?int $userId = null): Ticket
    {
        return $this->creation->create($data, $userId);
    }

    public function update(int $id, array $data, ?int $userId = null, array $context = []): Ticket
    {
        return $this->updates->update($id, $data, $userId, $context);
    }

    public function reply(int $id, int $userId, string $body, bool $isInternal = false, array $attachments = []): TicketMessage
    {
        return $this->replies->reply($id, $userId, $body, $isInternal, $attachments);
    }

    public function addContactMessage(
        int $ticketId,
        int $contactId,
        string $body,
        int $channelId,
        ?string $externalId = null,
        bool $fromEmail = false,
    ): TicketMessage {
        return $this->replies->addContactMessage($ticketId, $contactId, $body, $channelId, $externalId, $fromEmail);
    }

    public function storeInboundAttachments(int $ticketId, TicketMessage $message, array $attachments): void
    {
        $this->replies->storeInboundAttachments($ticketId, $message, $attachments);
    }

    public function addAttachment(int $id, int $userId, UploadedFile $file): TicketAttachment
    {
        return $this->watchers->addAttachment($id, $userId, $file);
    }

    public function addWatcher(int $id, int $userId): Ticket
    {
        return $this->watchers->addWatcher($id, $userId);
    }

    public function removeWatcher(int $id, int $userId): Ticket
    {
        return $this->watchers->removeWatcher($id, $userId);
    }

    public function merge(int $targetId, int $sourceId, int $userId, bool $importConversation = true): Ticket
    {
        return $this->merges->merge($targetId, $sourceId, $userId, $importConversation);
    }

    public function split(int $id, int $fromMessageId, int $userId, ?string $subject = null): Ticket
    {
        return $this->splits->split($id, $fromMessageId, $userId, $subject);
    }

    public function statuses(): Collection
    {
        return $this->tickets->statuses();
    }

    public function priorities(): Collection
    {
        return $this->tickets->priorities();
    }

    public function openCount(): int
    {
        return $this->tickets->countOpen();
    }

    public function statusSummary(): Collection
    {
        return $this->tickets->countByStatus();
    }
}
