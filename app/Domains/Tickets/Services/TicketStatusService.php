<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Tickets\Repositories\TicketStatusRepository;
use App\Domains\Tickets\Support\TicketFormReferenceCache;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class TicketStatusService
{
    public function __construct(
        private TicketStatusRepository $statuses,
        private AuditRecorder $audit,
    ) {
    }

    public function all(): Collection
    {
        return $this->statuses->all();
    }

    public function create(array $data): TicketStatus
    {
        $name = trim($data['name'] ?? '');

        if ($name === '') {
            throw new InvalidArgumentException('Status name is required.');
        }

        $status = $this->statuses->create([
            'name' => $name,
            'color' => $data['color'] ?? 'slate',
            'sort_order' => $data['sort_order'] ?? null,
            'is_closed' => (bool) ($data['is_closed'] ?? false),
        ]);

        $this->audit->record('ticket_status.created', $status, ['name' => $status->name]);

        TicketFormReferenceCache::forget();

        return $status;
    }

    public function update(int $id, array $data): TicketStatus
    {
        $name = trim($data['name'] ?? '');

        if ($name === '') {
            throw new InvalidArgumentException('Status name is required.');
        }

        $status = $this->statuses->update($this->statuses->find($id), [
            'name' => $name,
            'color' => $data['color'] ?? 'slate',
            'sort_order' => $data['sort_order'] ?? null,
            'is_closed' => (bool) ($data['is_closed'] ?? false),
        ]);

        $this->audit->record('ticket_status.updated', $status, ['name' => $status->name]);

        TicketFormReferenceCache::forget();

        return $status;
    }

    public function delete(int $id): void
    {
        $status = $this->statuses->find($id);

        if ($this->statuses->isProtected($status)) {
            throw new InvalidArgumentException('This status cannot be deleted.');
        }

        if ($this->statuses->ticketCount($status) > 0) {
            throw new InvalidArgumentException('Reassign tickets before deleting this status.');
        }

        $this->statuses->delete($status);

        $this->audit->record('ticket_status.deleted', $status, ['name' => $status->name]);

        TicketFormReferenceCache::forget();
    }
}
