<?php

namespace App\Domains\ServiceDesk\Repositories;

use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\ServiceDesk\Models\ChangeRecord;
use App\Domains\Tickets\Models\Ticket;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ChangeRecordRepository
{
    public function findForTicket(int $ticketId): ?ChangeRecord
    {
        return ChangeRecord::query()->where('ticket_id', $ticketId)->first();
    }

    public function findOrCreateForTicket(int $ticketId): ChangeRecord
    {
        return ChangeRecord::query()->firstOrCreate(['ticket_id' => $ticketId]);
    }

    public function update(ChangeRecord $record, array $data): ChangeRecord
    {
        $record->fill($data);
        $record->save();

        return $record->fresh();
    }

    public function scheduledBetween(?CarbonInterface $from, ?CarbonInterface $to): Collection
    {
        $query = ChangeRecord::query()
            ->with(['ticket.status', 'ticket.priority', 'ticket.assignee'])
            ->whereHas('ticket', fn ($builder) => $builder->where('type', ServiceCatalogItem::TYPE_CHANGE))
            ->whereNotNull('planned_start');

        if ($from !== null) {
            $query->where(function ($builder) use ($from) {
                $builder->where('planned_end', '>=', $from)
                    ->orWhereNull('planned_end')
                    ->where('planned_start', '>=', $from);
            });
        }

        if ($to !== null) {
            $query->where('planned_start', '<=', $to);
        }

        return $query->orderBy('planned_start')->get();
    }

    public function snapshot(?ChangeRecord $record): ?array
    {
        if ($record === null) {
            return null;
        }

        $record->loadMissing('ticket.assignee');

        return [
            'id' => $record->id,
            'ticket_id' => $record->ticket_id,
            'risk' => $record->risk,
            'impact' => $record->impact,
            'rollback_plan' => $record->rollback_plan,
            'planned_start' => $record->planned_start?->toIso8601String(),
            'planned_end' => $record->planned_end?->toIso8601String(),
            'cab_user_ids' => $record->cab_user_ids ?? [],
            'cab_notes' => $record->cab_notes,
            'implementation_notes' => $record->implementation_notes,
        ];
    }

    public function calendarEntry(ChangeRecord $record): array
    {
        $ticket = $record->ticket;

        return [
            'id' => $record->id,
            'ticket_id' => $record->ticket_id,
            'number' => $ticket?->number,
            'subject' => $ticket?->subject,
            'risk' => $record->risk,
            'planned_start' => $record->planned_start?->toIso8601String(),
            'planned_end' => $record->planned_end?->toIso8601String(),
            'status' => $ticket?->status?->name,
            'assignee' => $ticket?->assignee?->name,
        ];
    }
}
