<?php

namespace App\Domains\ServiceDesk\Services;

use App\Domains\Billing\Services\BillingService;
use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\ServiceDesk\Models\ChangeRecord;
use App\Domains\ServiceDesk\Repositories\ChangeRecordRepository;
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
        private BillingService $billing,
    ) {
    }

    public function ensureForTicket(Ticket $ticket): ?ChangeRecord
    {
        if (! $this->billing->canUseFeature('service_desk')) {
            return null;
        }

        if ($ticket->type !== ServiceCatalogItem::TYPE_CHANGE) {
            return null;
        }

        return $this->records->findOrCreateForTicket($ticket->id);
    }

    public function snapshotForTicket(int $ticketId): ?array
    {
        if (! $this->billing->canUseFeature('service_desk')) {
            return null;
        }

        $ticket = $this->tickets->find($ticketId);

        if ($ticket->type !== ServiceCatalogItem::TYPE_CHANGE) {
            return null;
        }

        $record = $this->records->findOrCreateForTicket($ticketId);

        return $this->records->snapshot($record);
    }

    public function update(int $ticketId, array $data): array
    {
        $this->billing->assertFeature('service_desk');
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

        $updated = $this->records->update($record, $data);

        return $this->records->snapshot($updated);
    }

    public function calendar(?string $from = null, ?string $to = null): array
    {
        $this->billing->assertFeature('service_desk');

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
}
