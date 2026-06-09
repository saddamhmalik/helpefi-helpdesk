<?php

namespace App\Domains\Tickets\Repositories;

use App\Domains\Brands\Models\Brand;
use App\Domains\Brands\Services\BrandService;
use App\Domains\Settings\Repositories\HelpdeskSettingRepository;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketAttachment;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Tickets\Support\TicketFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TicketRepository
{
    public function __construct(
        private HelpdeskSettingRepository $helpdeskSettings,
        private BrandService $brands,
    ) {
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->paginateFiltered([], $perPage);
    }

    public function paginateFiltered(array $filters, int $perPage = 15, ?int $watchingUserId = null): LengthAwarePaginator
    {
        $filters = TicketFilters::normalize($filters);

        $query = Ticket::query()
            ->with([
                'contact:id,name,email',
                'status:id,name,slug,color',
                'priority:id,name,slug',
                'assignee' => fn ($query) => $query
                    ->select(['id', 'name'])
                    ->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['admin', 'agent'])),
                'channel:id,name,slug,type',
            ])
            ->whereNull('merged_into_ticket_id')
            ->orderByDesc('updated_at');

        if (! empty($filters['status_id'])) {
            $query->where('ticket_status_id', $filters['status_id']);
        }

        if (! empty($filters['priority_id'])) {
            $query->where('ticket_priority_id', $filters['priority_id']);
        }

        if (! empty($filters['mine']) && $watchingUserId) {
            $query->where('assigned_to', $watchingUserId);
        } elseif (! empty($filters['unassigned'])) {
            $query->whereNull('assigned_to');
        } elseif (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (! empty($filters['channel_id'])) {
            $query->where('channel_id', $filters['channel_id']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['team_id'])) {
            $query->where('team_id', $filters['team_id']);
        }

        if (! empty($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (! empty($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%")
                    ->orWhereHas('contact', function ($contact) use ($search) {
                        $contact->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['contact'])) {
            $contact = $filters['contact'];
            $query->whereHas('contact', function ($query) use ($contact) {
                $query->where('name', 'like', "%{$contact}%")
                    ->orWhere('email', 'like', "%{$contact}%");
            });
        }

        if (! empty($filters['watching']) && $watchingUserId) {
            $query->whereHas('watchers', fn ($q) => $q->where('user_id', $watchingUserId));
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): Ticket
    {
        return Ticket::query()
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
                'messages.user:id,name,email',
                'messages.contact:id,name,email',
                'messages.channel:id,name,slug,type',
                'messages.attachments',
                'attachments.user:id,name',
                'watchers:id,name,email',
                'ccs.contact:id,name,email',
                'mergedInto:id,number,subject',
                'mergedTickets:id,number,subject',
                'slaTimer.policy.businessHours',
            ])
            ->findOrFail($id);
    }

    public function create(array $data): Ticket
    {
        $data['number'] = $this->nextNumber($data['brand_id'] ?? null);

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

    public function addAttachment(Ticket $ticket, int $userId, UploadedFile $file): TicketAttachment
    {
        $path = $file->store('ticket-attachments', 'public');

        return $ticket->attachments()->create([
            'user_id' => $userId,
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
        ]);
    }

    public function hasMatchingAttachment(Ticket $ticket, string $filename, int $size): bool
    {
        return $ticket->attachments()
            ->where('filename', $filename)
            ->where('size', $size)
            ->exists();
    }

    public function addMessageAttachmentFromUpload(
        Ticket $ticket,
        TicketMessage $message,
        int $userId,
        UploadedFile $file,
    ): TicketAttachment {
        $path = $file->store('ticket-attachments', 'public');

        return $ticket->attachments()->create([
            'ticket_message_id' => $message->id,
            'user_id' => $userId,
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
        ]);
    }

    public function addMessageAttachment(
        Ticket $ticket,
        TicketMessage $message,
        string $filename,
        string $content,
        ?string $mimeType = null,
        ?int $userId = null,
    ): TicketAttachment {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename) ?: 'attachment';
        $path = 'ticket-attachments/'.Str::uuid().'_'.$safeName;
        Storage::disk('public')->put($path, $content);

        return $ticket->attachments()->create([
            'ticket_message_id' => $message->id,
            'user_id' => $userId,
            'filename' => $filename,
            'path' => $path,
            'mime_type' => $mimeType,
            'size' => strlen($content),
        ]);
    }

    public function addWatcher(Ticket $ticket, int $userId): void
    {
        $ticket->watchers()->syncWithoutDetaching([$userId]);
    }

    public function removeWatcher(Ticket $ticket, int $userId): void
    {
        $ticket->watchers()->detach($userId);
    }

    public function merge(Ticket $target, Ticket $source, int $userId): Ticket
    {
        DB::transaction(function () use ($target, $source, $userId) {
            TicketMessage::query()
                ->where('ticket_id', $source->id)
                ->update(['ticket_id' => $target->id]);

            TicketAttachment::query()
                ->where('ticket_id', $source->id)
                ->update(['ticket_id' => $target->id]);

            $watcherIds = $source->watchers()->pluck('users.id')->all();
            if ($watcherIds) {
                $target->watchers()->syncWithoutDetaching($watcherIds);
            }

            $closedStatus = TicketStatus::query()->where('is_closed', true)->orderBy('sort_order')->first();

            $source->update([
                'merged_into_ticket_id' => $target->id,
                'ticket_status_id' => $closedStatus?->id ?? $source->ticket_status_id,
                'closed_at' => now(),
            ]);

            $target->messages()->create([
                'user_id' => $userId,
                'body' => "Ticket {$source->number} was merged into this ticket.",
                'is_internal' => true,
            ]);
        });

        return $this->find($target->id);
    }

    public function split(Ticket $ticket, int $fromMessageId, int $userId, ?string $subject = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $fromMessageId, $userId, $subject) {
            $message = $ticket->messages()->findOrFail($fromMessageId);
            $messageIds = $ticket->messages()
                ->where('created_at', '>=', $message->created_at)
                ->pluck('id');

            $newTicket = $this->create([
                'subject' => $subject ?? "Split from {$ticket->number}",
                'description' => null,
                'contact_id' => $ticket->contact_id,
                'assigned_to' => $ticket->assigned_to,
                'ticket_status_id' => $ticket->ticket_status_id,
                'ticket_priority_id' => $ticket->ticket_priority_id,
            ]);

            TicketMessage::query()->whereIn('id', $messageIds)->update(['ticket_id' => $newTicket->id]);

            TicketAttachment::query()
                ->whereIn('ticket_message_id', $messageIds)
                ->update(['ticket_id' => $newTicket->id]);

            $ticket->messages()->create([
                'user_id' => $userId,
                'body' => "Split messages into ticket {$newTicket->number}.",
                'is_internal' => true,
            ]);

            $newTicket->messages()->create([
                'user_id' => $userId,
                'body' => "Created by split from ticket {$ticket->number}.",
                'is_internal' => true,
            ]);

            return $this->find($newTicket->id);
        });
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
            ->whereHas('status', fn ($q) => $q->where('is_closed', false))
            ->count();
    }

    public function countByStatus(): Collection
    {
        return TicketStatus::query()
            ->withCount(['tickets' => fn ($q) => $q->whereNull('merged_into_ticket_id')])
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'color']);
    }

    private function nextNumber(?int $brandId = null): string
    {
        $prefix = $this->resolvePrefix($brandId);
        $query = Ticket::query()->when($brandId, fn ($q) => $q->where('brand_id', $brandId));
        $latest = (clone $query)->orderByDesc('id')->value('number');
        $sequence = 1;

        if ($latest && str_starts_with(strtoupper($latest), strtoupper($prefix))) {
            $sequence = ((int) Str::after($latest, $prefix)) + 1;
        } elseif ($latest) {
            $sequence = (clone $query)->count() + 1;
        }

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    private function resolvePrefix(?int $brandId): string
    {
        if ($brandId) {
            $brand = Brand::query()->find($brandId);

            if ($brand) {
                $prefix = $this->brands->ticketNumberPrefix($brand);

                if ($prefix) {
                    return $prefix;
                }
            }
        }

        return $this->helpdeskSettings->current()->ticket_number_prefix ?: 'HD-';
    }
}
