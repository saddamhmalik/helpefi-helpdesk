<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Notifications\CustomerReplyNotification;
use App\Domains\Notifications\Notifications\SlaBreachNotification;
use App\Domains\Notifications\Notifications\TicketAssignedNotification;
use App\Domains\ServiceDesk\Notifications\ApprovalDecidedNotification;
use App\Domains\ServiceDesk\Notifications\ApprovalRequestedNotification;
use App\Domains\ServiceDesk\Models\ApprovalRequest;
use App\Domains\Notifications\Repositories\AgentNotificationRepository;
use App\Domains\Notifications\Repositories\NotificationSettingRepository;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class NotificationService
{
    public function __construct(
        private AgentNotificationRepository $notifications,
        private NotificationSettingRepository $settings,
    ) {
    }

    public function settingsSnapshot(): array
    {
        $setting = $this->settings->current();

        return [
            'email_enabled' => $setting->email_enabled,
            'notify_ticket_assigned' => $setting->notify_ticket_assigned,
            'notify_customer_reply' => $setting->notify_customer_reply,
            'notify_sla_breach' => $setting->notify_sla_breach,
            'notify_approval_pending' => $setting->notify_approval_pending,
        ];
    }

    public function updateSettings(array $data): array
    {
        $this->settings->update($this->settings->current(), [
            'email_enabled' => $data['email_enabled'] ?? false,
            'notify_ticket_assigned' => $data['notify_ticket_assigned'] ?? true,
            'notify_customer_reply' => $data['notify_customer_reply'] ?? true,
            'notify_sla_breach' => $data['notify_sla_breach'] ?? true,
            'notify_approval_pending' => $data['notify_approval_pending'] ?? true,
        ]);

        return $this->settingsSnapshot();
    }

    public function inboxSummary(User $user): array
    {
        return [
            'unread_count' => $this->notifications->unreadCount($user),
            'recent' => $this->mapNotifications($this->notifications->recent($user)),
        ];
    }

    public function list(User $user, int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $paginator = $this->notifications->paginate($user, $perPage, $filters);
        $paginator->getCollection()->transform(fn ($notification) => $this->formatNotification($notification));

        return $paginator;
    }

    public function clearRead(User $user): int
    {
        return $this->notifications->deleteRead($user);
    }

    public function markRead(User $user, string $id): void
    {
        $this->notifications->markRead($user, $id);
    }

    public function markAllRead(User $user): void
    {
        $this->notifications->markAllRead($user);
    }

    public function ticketAssigned(Ticket $ticket, ?int $actorId = null): void
    {
        if (! $ticket->assigned_to || $ticket->assigned_to === $actorId) {
            return;
        }

        $assignee = User::query()->find($ticket->assigned_to);

        if ($assignee) {
            $assignee->notify(new TicketAssignedNotification($ticket));
        }
    }

    public function customerReply(Ticket $ticket, TicketMessage $message): void
    {
        if ($message->is_internal || ! $message->contact_id) {
            return;
        }

        $ticket->loadMissing(['assignee', 'watchers']);
        $preview = mb_strlen($message->body) > 120
            ? mb_substr($message->body, 0, 120).'…'
            : $message->body;

        $recipients = $this->ticketRecipients($ticket, excludeUserId: $message->user_id);

        foreach ($recipients as $recipient) {
            $recipient->notify(new CustomerReplyNotification($ticket, $preview));
        }
    }

    public function slaBreached(Ticket $ticket, string $breachType): void
    {
        $ticket->loadMissing(['assignee', 'watchers']);

        foreach ($this->ticketRecipients($ticket) as $recipient) {
            $recipient->notify(new SlaBreachNotification($ticket, $breachType));
        }
    }

    public function slaEscalated(Ticket $ticket, int $level, string $breachType): void
    {
        $ticket->loadMissing(['assignee', 'watchers', 'team.lead', 'department.head']);

        $recipients = $this->ticketRecipients($ticket);

        if ($ticket->team?->lead) {
            $recipients->put($ticket->team->lead->id, $ticket->team->lead);
        }

        if ($ticket->department?->head) {
            $recipients->put($ticket->department->head->id, $ticket->department->head);
        }

        foreach ($recipients->values() as $recipient) {
            $recipient->notify(new SlaBreachNotification($ticket, "{$breachType}_escalation_l{$level}"));
        }
    }

    public function approvalPending(ApprovalRequest $request, User $approver, string $reviewUrl): void
    {
        $approver->notify(new ApprovalRequestedNotification($request, $reviewUrl));
    }

    public function approvalDecided(ApprovalRequest $request, bool $approved): void
    {
        $request->loadMissing(['requestedBy']);

        if ($request->requestedBy) {
            $request->requestedBy->notify(new ApprovalDecidedNotification($request, $approved));
        }
    }

    private function ticketRecipients(Ticket $ticket, ?int $excludeUserId = null): Collection
    {
        $users = collect();

        if ($ticket->assignee && $ticket->assignee->id !== $excludeUserId) {
            $users->put($ticket->assignee->id, $ticket->assignee);
        }

        foreach ($ticket->watchers as $watcher) {
            if ($watcher->id !== $excludeUserId) {
                $users->put($watcher->id, $watcher);
            }
        }

        return $users->values();
    }

    private function mapNotifications(Collection $notifications): array
    {
        return $notifications->map(fn ($notification) => $this->formatNotification($notification))->all();
    }

    public function formatNotification($notification): array
    {
        return $this->mapNotification($notification);
    }

    private function mapNotification($notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? 'unknown',
            'message' => $data['message'] ?? '',
            'url' => $data['url'] ?? null,
            'ticket_id' => $data['ticket_id'] ?? null,
            'ticket_number' => $data['ticket_number'] ?? null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
