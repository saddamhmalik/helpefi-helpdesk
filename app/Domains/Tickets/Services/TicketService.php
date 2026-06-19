<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Assignment\Services\AssignmentService;
use App\Domains\Automation\Events\TicketAutomationTrigger;
use App\Domains\Automation\Models\AutomationRule;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Channels\Models\Channel;
use App\Domains\Channels\Repositories\ChannelRepository;
use App\Domains\Channels\Services\ClosedTicketInboundReopenService;
use App\Domains\Channels\Services\OutboundMailService;
use App\Domains\Channels\Services\TwilioMessagingService;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Performance\Services\PerformanceService;
use App\Domains\Sla\Services\SlaService;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Settings\Services\AutoFirstResponseService;
use App\Domains\Settings\Services\HelpdeskSettingService;
use App\Domains\ServiceDesk\Services\ApprovalService;
use App\Domains\ServiceDesk\Services\ChangeRecordService;
use App\Domains\ServiceDesk\Services\ProblemRecordService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketAttachment;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Domains\Workforce\Models\Team;
use App\Domains\Workforce\Services\WorkforceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use App\Domains\Tickets\Support\MessageBodySanitizer;
use App\Domains\Realtime\Services\RealtimePublisher;
use App\Domains\Realtime\Support\RealtimeMessagePayload;
use App\Domains\Workspace\Services\TicketPresenceService;
use InvalidArgumentException;

class TicketService
{
    public function __construct(
        private TicketRepository $tickets,
        private SlaService $sla,
        private ChannelRepository $channels,
        private BillingService $billing,
        private NotificationService $notifications,
        private OutboundMailService $outboundMail,
        private TwilioMessagingService $messaging,
        private AuditRecorder $audit,
        private WorkforceService $workforce,
        private PerformanceService $performance,
        private HelpdeskSettingService $helpdeskSettings,
        private AutoFirstResponseService $autoFirstResponse,
        private ContactService $contacts,
        private TicketCcService $ticketCcs,
        private TicketPresenceService $presence,
        private AssignmentService $assignment,
        private RealtimePublisher $realtime,
        private TicketReadService $ticketReads,
        private ApprovalService $approvals,
        private ChangeRecordService $changeRecords,
        private ProblemRecordService $problemRecords,
        private ClosedTicketInboundReopenService $closedTicketReopen,
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

    public function show(int $id): Ticket
    {
        $ticket = $this->tickets->find($id);

        if (! $ticket->slaTimer) {
            $this->sla->applyToTicket($ticket);

            return $this->tickets->find($id);
        }

        return $ticket;
    }

    public function create(array $data, ?int $userId = null): Ticket
    {
        $this->billing->assertLimit('tickets_monthly', 1);

        [$ccEmails, $requesterEmail, $requesterName] = $this->extractPeopleFields($data);
        $data = $this->resolveRequester($data, $requesterEmail, $requesterName, $userId);

        if (empty($data['channel_id'])) {
            $data['channel_id'] = $this->channels->findActiveBySlug('web')?->id;
        }

        $data = $this->applyWorkforceRouting($data);
        $data = $this->assignment->enrichUnassignedTicket($data);

        if (array_key_exists('custom_fields', $data)) {
            $data['custom_fields'] = $this->resolveTicketCustomFields($data);
        }

        if (array_key_exists('description', $data)) {
            $data['description'] = $this->normalizeRichText($data['description']);
        }

        $ticket = $this->tickets->create($data);
        $this->ticketCcs->sync($ticket, $ccEmails, $userId);
        $this->sla->applyToTicket($ticket);
        $this->createInitialMessageFromDescription($ticket, $userId);

        $ticket = $this->tickets->find($ticket->id);
        TicketAutomationTrigger::dispatch($ticket, AutomationRule::TRIGGER_TICKET_CREATED);

        $this->audit->record('ticket.created', $ticket, [
            'number' => $ticket->number,
            'subject' => $ticket->subject,
        ], $userId);

        $this->approvals->evaluateForNewTicket($ticket, $userId);
        $this->changeRecords->ensureForTicket($ticket);
        $this->problemRecords->ensureForTicket($ticket);

        return $ticket;
    }

    public function update(int $id, array $data, ?int $userId = null, array $context = []): Ticket
    {
        $ticket = $this->tickets->find($id);
        [$ccEmails, $requesterEmail, $requesterName] = $this->extractPeopleFields($data);

        if ($requesterEmail !== null || array_key_exists('contact_id', $data)) {
            $data = $this->resolveRequester($data, $requesterEmail, $requesterName, $userId, $ticket);
        }

        $before = $ticket->only(array_keys($data));

        if (isset($data['ticket_status_id'])) {
            $status = $this->tickets->statuses()->firstWhere('id', $data['ticket_status_id']);

            if ($status?->is_closed) {
                $data['closed_at'] = now();
            } else {
                $data['closed_at'] = null;
            }
        }

        if (array_key_exists('custom_fields', $data)) {
            $data['custom_fields'] = $this->resolveTicketCustomFields($data);
        }

        if (array_key_exists('description', $data)) {
            $data['description'] = $this->normalizeRichText($data['description']);
        }

        $ticket = $this->tickets->update(
            $ticket,
            $this->assignment->enrichUnassignedTicket($this->applyWorkforceRouting($data, $ticket), $ticket),
        );

        if ($ccEmails !== null) {
            $ticket->load('contact');
            $this->ticketCcs->sync($ticket, $ccEmails, $userId);
        }

        if (isset($data['ticket_status_id'])) {
            $status = $this->tickets->statuses()->firstWhere('id', $data['ticket_status_id']);

            if ($status?->is_closed) {
                $this->sla->recordResolution($ticket);

                if ($ticket->assigned_to) {
                    $this->performance->record($ticket->assigned_to, 'ticket_resolved', $ticket->id);
                }
            }
        }

        $ticket = $this->tickets->find($ticket->id);
        TicketAutomationTrigger::dispatch($ticket, AutomationRule::TRIGGER_TICKET_UPDATED, array_merge([
            'changed' => array_keys($data),
        ], $context));

        $this->audit->recordChanges('ticket.updated', $ticket, $before, $ticket->only(array_keys($data)), [
            'number' => $ticket->number,
        ]);

        $this->presence->pulse($ticket->id);
        $this->broadcastTicketSnapshot($ticket);

        return $ticket;
    }

    public function reply(int $id, int $userId, string $body, bool $isInternal = false, array $attachments = []): TicketMessage
    {
        $body = MessageBodySanitizer::sanitize($body);
        $uploads = array_values(array_filter($attachments, fn ($file) => $file instanceof UploadedFile));

        if (MessageBodySanitizer::isEmpty($body) && $uploads === []) {
            throw new InvalidArgumentException('Message body or attachment is required.');
        }

        $ticket = $this->tickets->find($id);

        if (! $isInternal && $this->closedTicketReopen->maybeReopenOnAgentReply($ticket)) {
            $ticket = $this->tickets->find($id);
            $this->broadcastTicketSnapshot($ticket);
        }

        $channelId = $ticket->channel_id && $this->channels->find($ticket->channel_id)?->type === 'email'
            ? $ticket->channel_id
            : $this->channels->findActiveBySlug('web')?->id;

        $message = $this->tickets->addMessage($ticket, [
            'user_id' => $userId,
            'body' => MessageBodySanitizer::isEmpty($body) ? '' : $body,
            'is_internal' => $isInternal,
            'channel_id' => $channelId,
        ]);

        foreach ($uploads as $file) {
            $this->tickets->addMessageAttachmentFromUpload($ticket, $message, $userId, $file);
        }

        $message->load('attachments');

        $user = \App\Models\User::query()->find($userId);

        if (! $isInternal && $this->sla->isAgentUser($user)) {
            $this->sla->recordFirstResponse($this->tickets->find($id));
            $ticketForMail = $this->tickets->find($id);
            $ticketForMail->loadMissing('channel');
            $replyChannelType = $ticketForMail->channel?->type
                ?? $this->channels->find($channelId)?->type;

            if ($replyChannelType === Channel::TYPE_WHATSAPP || $replyChannelType === Channel::TYPE_SMS) {
                $ticketForMail->loadMissing('contact');
                $phone = $ticketForMail->contact?->phone;

                if ($phone) {
                    $this->messaging->send($phone, $message->body, $replyChannelType);
                }
            } elseif ($replyChannelType !== Channel::TYPE_CHAT) {
                $this->outboundMail->sendTicketReply($ticketForMail, $message, $user);
            }
        }

        $this->audit->record('ticket.replied', $ticket, [
            'number' => $ticket->number,
            'message_id' => $message->id,
            'is_internal' => $isInternal,
        ], $userId);

        $this->presence->pulse($id);
        $this->broadcastMessage($message, ! $isInternal);

        return $message;
    }

    public function addContactMessage(
        int $ticketId,
        int $contactId,
        string $body,
        int $channelId,
        ?string $externalId = null,
        bool $fromEmail = false,
    ): TicketMessage {
        $ticket = $this->tickets->find($ticketId);

        if ($this->closedTicketReopen->maybeReopenOnCustomerMessage($ticket, $body, $fromEmail)) {
            $ticket = $this->tickets->find($ticketId);
            $this->broadcastTicketSnapshot($ticket);
        }

        $message = $this->tickets->addMessage($ticket, [
            'contact_id' => $contactId,
            'body' => $body,
            'is_internal' => false,
            'channel_id' => $channelId,
            'external_id' => $externalId,
        ]);

        TicketAutomationTrigger::dispatch(
            $this->tickets->find($ticketId),
            AutomationRule::TRIGGER_CUSTOMER_MESSAGE,
            ['message_body' => strip_tags($body)],
        );

        $ticket = $this->tickets->find($ticketId);
        $this->notifications->customerReply($ticket, $message);

        if ($ticket->messages()->whereNotNull('contact_id')->count() === 1) {
            $this->autoFirstResponse->sendIfEnabled($ticket, $message);
        }

        $this->audit->record('ticket.customer_message', $ticket, [
            'number' => $ticket->number,
            'message_id' => $message->id,
            'contact_id' => $contactId,
        ]);

        $this->presence->pulse($ticketId);
        $this->broadcastMessage($message, true);

        return $message;
    }

    public function storeInboundAttachments(int $ticketId, TicketMessage $message, array $attachments): void
    {
        if ($attachments === []) {
            return;
        }

        $ticket = $this->tickets->find($ticketId);
        $seen = [];

        foreach ($attachments as $attachment) {
            if (empty($attachment['filename']) || empty($attachment['content'])) {
                continue;
            }

            $raw = $attachment['content'];
            $decoded = base64_decode($raw, true);
            $content = ($decoded !== false && base64_encode($decoded) === $raw) ? $decoded : $raw;

            if ($content === false || $content === '') {
                continue;
            }

            $filename = $attachment['filename'];
            $size = strlen($content);
            $fingerprint = hash('sha256', $filename.'|'.$size.'|'.hash('sha256', $content));

            if (isset($seen[$fingerprint]) || $this->tickets->hasMatchingAttachment($ticket, $filename, $size)) {
                continue;
            }

            $seen[$fingerprint] = true;

            $this->tickets->addMessageAttachment(
                $ticket,
                $message,
                $filename,
                $content,
                $attachment['mime_type'] ?? null,
            );
        }
    }

    public function addAttachment(int $id, int $userId, UploadedFile $file): TicketAttachment
    {
        $ticket = $this->tickets->find($id);
        $attachment = $this->tickets->addAttachment($ticket, $userId, $file);

        $this->audit->record('ticket.attachment_added', $ticket, [
            'number' => $ticket->number,
            'filename' => $attachment->filename,
        ], $userId);

        return $attachment;
    }

    public function addWatcher(int $id, int $userId): Ticket
    {
        $ticket = $this->tickets->find($id);
        $this->tickets->addWatcher($ticket, $userId);

        $this->audit->record('ticket.watcher_added', $ticket, [
            'number' => $ticket->number,
            'watcher_id' => $userId,
        ]);

        return $this->tickets->find($id);
    }

    public function removeWatcher(int $id, int $userId): Ticket
    {
        $ticket = $this->tickets->find($id);
        $this->tickets->removeWatcher($ticket, $userId);

        $this->audit->record('ticket.watcher_removed', $ticket, [
            'number' => $ticket->number,
            'watcher_id' => $userId,
        ]);

        return $this->tickets->find($id);
    }

    public function merge(int $targetId, int $sourceId, int $userId, bool $importConversation = true): Ticket
    {
        if ($targetId === $sourceId) {
            throw new InvalidArgumentException('Cannot merge a ticket into itself.');
        }

        $target = $this->tickets->find($targetId);
        $source = $this->tickets->find($sourceId);

        if ($source->merged_into_ticket_id) {
            throw new InvalidArgumentException('Source ticket is already merged.');
        }

        if ($target->merged_into_ticket_id) {
            throw new InvalidArgumentException('Target ticket is already merged.');
        }

        $sourceHadMessages = $source->messages()->exists();

        $merged = $this->tickets->merge($target, $source, $userId, $importConversation);

        if ($importConversation && ! $sourceHadMessages) {
            $this->importMergedTicketDescription($merged, $source);
            $merged = $this->tickets->find($merged->id);
        }

        $this->ticketCcs->mergeFromTicket($merged, $source, $userId);

        $this->audit->record('ticket.merged', $merged, [
            'target_number' => $merged->number,
            'source_number' => $source->number,
            'source_id' => $source->id,
            'import_conversation' => $importConversation,
        ], $userId);

        return $this->tickets->find($merged->id);
    }

    public function split(int $id, int $fromMessageId, int $userId, ?string $subject = null): Ticket
    {
        $this->billing->assertLimit('tickets_monthly', 1);

        $source = $this->tickets->find($id);
        $newTicket = $this->tickets->split($source, $fromMessageId, $userId, $subject);

        $this->audit->record('ticket.split', $newTicket, [
            'from_ticket_number' => $source->number,
            'from_ticket_id' => $source->id,
            'from_message_id' => $fromMessageId,
        ], $userId);

        return $newTicket;
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

    private function applyWorkforceRouting(array $data, ?Ticket $ticket = null): array
    {
        if (! empty($data['team_id']) && empty($data['department_id'])) {
            $team = Team::query()->find($data['team_id']);
            $data['department_id'] = $team?->department_id;
        }

        if (empty($data['department_id']) && empty($data['team_id'])) {
            $assigneeId = $data['assigned_to'] ?? $ticket?->assigned_to;

            if ($assigneeId) {
                $data = array_merge($data, array_filter($this->workforce->resolveRoutingForAssignee($assigneeId)));
            }
        }

        return $data;
    }

    private function extractPeopleFields(array &$data): array
    {
        $ccEmails = array_key_exists('cc_emails', $data) ? (array) $data['cc_emails'] : null;
        $requesterEmail = array_key_exists('requester_email', $data)
            ? trim((string) $data['requester_email'])
            : null;
        $requesterName = array_key_exists('requester_name', $data)
            ? trim((string) $data['requester_name'])
            : null;

        unset($data['cc_emails'], $data['requester_email'], $data['requester_name']);

        if ($requesterEmail === '') {
            $requesterEmail = null;
        }

        if ($requesterName === '') {
            $requesterName = null;
        }

        return [$ccEmails, $requesterEmail, $requesterName];
    }

    private function resolveRequester(
        array $data,
        ?string $requesterEmail,
        ?string $requesterName,
        ?int $userId,
        ?Ticket $ticket = null,
    ): array {
        if (! empty($data['contact_id'])) {
            return $data;
        }

        if ($requesterEmail) {
            $name = $requesterName ?: explode('@', $requesterEmail)[0];
            $contact = $this->contacts->findOrCreateByEmail($requesterEmail, $name, $userId);
            $data['contact_id'] = $contact->id;

            return $data;
        }

        if (array_key_exists('contact_id', $data) && ($data['contact_id'] === '' || $data['contact_id'] === null)) {
            if ($ticket && ! $requesterEmail) {
                unset($data['contact_id']);
            } else {
                $data['contact_id'] = null;
            }
        } elseif (! array_key_exists('contact_id', $data) && $ticket) {
            $data['contact_id'] = $ticket->contact_id;
        }

        return $data;
    }

    private function resolveTicketCustomFields(array $data): array
    {
        if (! empty($data['brand_id'])) {
            $brand = \App\Domains\Brands\Models\Brand::query()->find($data['brand_id']);

            return $this->helpdeskSettings->resolveFieldValuesForBrand('ticket', $data['custom_fields'] ?? [], $brand);
        }

        return $this->helpdeskSettings->resolveFieldValues('ticket', $data['custom_fields'] ?? []);
    }

    private function normalizeRichText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $sanitized = MessageBodySanitizer::sanitize($value);

        return MessageBodySanitizer::isEmpty($sanitized) ? null : $sanitized;
    }

    private function createInitialMessageFromDescription(Ticket $ticket, ?int $userId): void
    {
        $description = $ticket->description;

        if ($description === null || MessageBodySanitizer::isEmpty($description)) {
            return;
        }

        $channelId = $ticket->channel_id ?? $this->channels->findActiveBySlug('web')?->id;

        if ($ticket->contact_id) {
            $this->tickets->addMessage($ticket, [
                'contact_id' => $ticket->contact_id,
                'body' => $description,
                'is_internal' => false,
                'channel_id' => $channelId,
            ]);

            return;
        }

        if ($userId) {
            $this->tickets->addMessage($ticket, [
                'user_id' => $userId,
                'body' => $description,
                'is_internal' => false,
                'channel_id' => $channelId,
            ]);
        }
    }

    private function importMergedTicketDescription(Ticket $target, Ticket $source): void
    {
        $description = $this->normalizeRichText($source->description);

        if ($description === null) {
            return;
        }

        $channelId = $source->channel_id ?? $target->channel_id ?? $this->channels->findActiveBySlug('web')?->id;

        $payload = [
            'body' => $description,
            'is_internal' => false,
            'channel_id' => $channelId,
            'merged_from_ticket_id' => $source->id,
        ];

        if ($source->contact_id) {
            $payload['contact_id'] = $source->contact_id;
        }

        $this->tickets->addMessage($target, $payload);
    }

    private function broadcastMessage(TicketMessage $message, bool $includeChatChannel): void
    {
        $message->loadMissing(['user:id,name,email,avatar_type,avatar_path', 'contact:id,name']);

        $this->realtime->ticketMessage(
            $message->ticket_id,
            RealtimeMessagePayload::fromMessage($message),
            $includeChatChannel && ! $message->is_internal
                ? RealtimeMessagePayload::chatSessionUuidForTicket($message->ticket_id)
                : null,
        );
    }

    private function broadcastTicketSnapshot(Ticket $ticket): void
    {
        $ticket->loadMissing([
            'status:id,name,slug,color',
            'priority:id,name,slug',
            'contact:id,name,email',
            'assignee:id,name',
        ]);

        $this->realtime->ticketUpdated($ticket->id, [
            'id' => $ticket->id,
            'number' => $ticket->number,
            'subject' => $ticket->subject,
            'ticket_status_id' => $ticket->ticket_status_id,
            'ticket_priority_id' => $ticket->ticket_priority_id,
            'assigned_to' => $ticket->assigned_to,
            'snoozed_until' => $ticket->snoozed_until?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'contact' => $ticket->contact,
            'assignee' => $ticket->assignee,
        ]);
    }
}
