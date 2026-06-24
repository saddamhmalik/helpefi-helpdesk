<?php

namespace App\Domains\Tickets\Repositories;

use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Support\AvatarSupport;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Tickets\Services\TicketNumberGenerator;
use App\Domains\Tickets\Services\TicketStatusLookup;
use App\Domains\Tickets\Support\TicketFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TicketRepository
{
    public function __construct(
        private TicketNumberGenerator $numbers,
        private TicketStatusLookup $statusLookup,
    ) {
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->paginateFiltered([], $perPage);
    }

    public function paginateFiltered(array $filters, int $perPage = 15, ?int $watchingUserId = null): LengthAwarePaginator
    {
        return $this
            ->filteredQuery($filters, $watchingUserId)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function exportFiltered(array $filters, ?int $watchingUserId, callable $callback): void
    {
        $this->filteredQuery($filters, $watchingUserId)
            ->chunkById(500, function ($tickets) use ($callback) {
                foreach ($tickets as $ticket) {
                    $callback($ticket);
                }
            });
    }

    private function filteredQuery(array $filters, ?int $watchingUserId = null)
    {
        $query = Ticket::query()
            ->with([
                'contact:id,name,email',
                'status:id,name,slug,color',
                'priority:id,name,slug',
                'assignee' => fn ($query) => $query
                    ->select(['id', 'name', 'email'])
                    ->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['admin', 'agent'])),
                'channel:id,name,slug,type',
                'department:id,name',
                'team:id,name',
            ])
            ->whereNull('merged_into_ticket_id')
            ->visibleInQueue()
            ->orderByDesc('updated_at');

        return TicketFilters::applyToQueueQuery($query, $filters, $watchingUserId);
    }

    public function find(int $id, int $messageLimit = 100, bool $includeInternal = true): Ticket
    {
        $ticket = Ticket::query()
            ->with([
                'channel:id,name,slug,type',
                'contact',
                'status',
                'priority',
                'assignee' => fn ($query) => $query
                    ->select(['id', 'name', 'email'])
                    ->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['admin', 'agent'])),
                'department:id,name,slug',
                'team:id,name,slug,department_id,lead_user_id',
                'team.lead:id,name,email',
                'department.head:id,name,email',
                'serviceCatalogItem:id,name,slug,ticket_type',
                'assets.type:id,name,slug',
                'attachments.user:id,name',
                'watchers:id,name,email',
                'ccs.contact:id,name,email',
                'mergedInto:id,number,subject',
                'mergedTickets:id,number,subject',
                'slaTimer.policy.businessHours',
            ])
            ->findOrFail($id);

        $this->loadRecentMessages($ticket, $messageLimit, $includeInternal);

        return $ticket;
    }

    public function findForWrite(int $id): Ticket
    {
        return Ticket::query()
            ->with([
                'status:id,is_closed,slug',
                'channel:id,type',
                'contact:id,email,phone',
            ])
            ->findOrFail($id);
    }

    public function findForBroadcast(int $id): Ticket
    {
        return Ticket::query()
            ->with([
                'status:id,name,slug,color',
                'priority:id,name,slug',
                'contact:id,name,email',
                'assignee:id,name',
            ])
            ->findOrFail($id);
    }

    public function findForMerge(int $id): Ticket
    {
        return Ticket::query()
            ->select(['id', 'number', 'description', 'contact_id', 'channel_id', 'merged_into_ticket_id'])
            ->findOrFail($id);
    }

    private function loadRecentMessages(Ticket $ticket, int $limit, bool $includeInternal = true): void
    {
        $messageIds = $ticket->messages()
            ->when(! $includeInternal, fn ($query) => $query->where('is_internal', false))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id');

        if ($messageIds->isEmpty()) {
            $ticket->setRelation('messages', collect());

            return;
        }

        $messages = TicketMessage::query()
            ->whereIn('id', $messageIds)
            ->with([
                'user:'.implode(',', AvatarSupport::USER_COLUMNS),
                'contact:id,name,email',
                'mergedFromTicket:id,number,subject,created_at',
                'channel:id,name,slug,type',
                'attachments',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $ticket->setRelation('messages', $messages);
    }

    public function findContactContext(int $id): Ticket
    {
        return Ticket::query()
            ->with(['contact.organization'])
            ->findOrFail($id);
    }

    public function create(array $data): Ticket
    {
        return DB::transaction(function () use ($data) {
            $data['number'] = $this->numbers->next($data['brand_id'] ?? null);

            if (blank($data['type'] ?? null)) {
                $data['type'] = ServiceCatalogItem::TYPE_INCIDENT;
            }

            return Ticket::query()->create($data);
        });
    }

    public function insert(array $data): Ticket
    {
        if (blank($data['type'] ?? null)) {
            $data['type'] = ServiceCatalogItem::TYPE_INCIDENT;
        }

        return Ticket::query()->create($data);
    }

    public function update(Ticket $ticket, array $data): Ticket
    {
        $ticket->update($data);

        return $ticket->fresh(['contact', 'status', 'priority', 'assignee']);
    }

    public function addMessage(Ticket $ticket, array $data): TicketMessage
    {
        return $ticket->messages()->create($data);
    }

    public function addWatcher(Ticket $ticket, int $userId): void
    {
        $ticket->watchers()->syncWithoutDetaching([$userId]);
    }

    public function removeWatcher(Ticket $ticket, int $userId): void
    {
        $ticket->watchers()->detach($userId);
    }

    public function statuses(): Collection
    {
        return TicketStatus::query()->orderBy('sort_order')->get();
    }

    public function priorities(): Collection
    {
        return TicketPriority::query()->orderBy('sort_order')->get();
    }

    public function countOpen(): int
    {
        return Ticket::query()
            ->whereNull('merged_into_ticket_id')
            ->tap(fn ($query) => $this->statusLookup->restrictToOpenTickets($query))
            ->count();
    }

    public function countByStatus(): Collection
    {
        return TicketStatus::query()
            ->withCount(['tickets' => fn ($q) => $q->whereNull('merged_into_ticket_id')])
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'color']);
    }
}
