<?php

namespace App\Domains\ServiceDesk\Services;

use App\Domains\Billing\Contracts\FeatureEntitlementChecker;
use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\ServiceDesk\Models\ChangeRecord;
use App\Domains\ServiceDesk\Repositories\ChangeRecordRepository;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Repositories\TicketRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ChangeRecordService
{
    public function __construct(
        private ChangeRecordRepository $records,
        private TicketRepository $tickets,
        private FeatureEntitlementChecker $entitlements,
        private AuditRecorder $audit,
    ) {
    }

    public function ensureForTicket(Ticket $ticket): ?ChangeRecord
    {
        if (! $this->entitlements->canUseFeature('service_desk')) {
            return null;
        }

        if ($ticket->type !== ServiceCatalogItem::TYPE_CHANGE) {
            return null;
        }

        return $this->records->findOrCreateForTicket($ticket->id);
    }

    public function snapshotForTicket(Ticket|int $ticketOrId): ?array
    {
        if (! $this->entitlements->canUseFeature('service_desk')) {
            return null;
        }

        $ticket = $this->resolveTicket($ticketOrId);

        if ($ticket->type !== ServiceCatalogItem::TYPE_CHANGE) {
            return null;
        }

        $record = $this->records->findForTicket($ticket->id);

        return $record ? $this->records->snapshot($record) : null;
    }

    public function update(int $ticketId, array $data): array
    {
        $this->entitlements->assertFeature('service_desk');
        $ticket = $this->assertChangeTicket($ticketId);
        $record = $this->records->findOrCreateForTicket($ticket->id);

        if (isset($data['planned_start'], $data['planned_end'])
            && $data['planned_start'] !== null
            && $data['planned_end'] !== null
            && Carbon::parse($data['planned_end'])->lt(Carbon::parse($data['planned_start']))) {
            throw ValidationException::withMessages([
                'planned_end' => 'Planned end must be after planned start.',
            ]);
        }

        $before = $record->only(array_keys($data));
        $updated = $this->records->update($record, $data);

        $this->audit->recordChanges(
            'service_desk.change_record_updated',
            $ticket,
            $before,
            $updated->only(array_keys($data)),
        );

        return $this->records->snapshot($updated);
    }

    public function calendar(?string $from = null, ?string $to = null): array
    {
        $this->entitlements->assertFeature('service_desk');

        $start = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
        $end = $to ? Carbon::parse($to)->endOfDay() : now()->endOfMonth();

        $entries = $this->records->scheduledBetween($start, $end)
            ->map(fn (ChangeRecord $record) => $this->records->calendarEntry($record))
            ->values()
            ->all();

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'entries' => $entries,
            'risk_options' => ChangeRecord::riskOptions(),
        ];
    }

    public function riskOptions(): array
    {
        return collect(ChangeRecord::riskOptions())
            ->map(fn (string $risk) => [
                'value' => $risk,
                'label' => ucfirst($risk),
            ])
            ->values()
            ->all();
    }

    private function assertChangeTicket(int $ticketId): Ticket
    {
        $ticket = $this->tickets->find($ticketId);

        if ($ticket->type !== ServiceCatalogItem::TYPE_CHANGE) {
            throw ValidationException::withMessages([
                'ticket' => 'Change details are only available on change tickets.',
            ]);
        }

        return $ticket;
    }

    private function resolveTicket(Ticket|int $ticketOrId): Ticket
    {
        return $ticketOrId instanceof Ticket ? $ticketOrId : $this->tickets->find($ticketOrId);
    }
}
