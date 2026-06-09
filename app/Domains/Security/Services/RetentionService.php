<?php

namespace App\Domains\Security\Services;

use App\Domains\Security\Repositories\AuditLogRepository;
use App\Domains\Security\Repositories\SecuritySettingRepository;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Models\TicketStatus;

class RetentionService
{
    public function __construct(
        private SecuritySettingRepository $settings,
        private AuditLogRepository $auditLogs,
        private AuditLogService $audit,
    ) {
    }

    public function purge(): array
    {
        $setting = $this->settings->current();
        $results = [
            'audit_logs' => 0,
            'tickets' => 0,
            'messages' => 0,
        ];

        if ($setting->audit_retention_days > 0) {
            $results['audit_logs'] = $this->auditLogs->deleteOlderThan($setting->audit_retention_days);
        }

        if ($setting->closed_ticket_retention_days) {
            $closedStatusIds = TicketStatus::query()->where('is_closed', true)->pluck('id');
            $cutoff = now()->subDays($setting->closed_ticket_retention_days);

            $ticketIds = Ticket::query()
                ->whereIn('ticket_status_id', $closedStatusIds)
                ->where('closed_at', '<', $cutoff)
                ->pluck('id');

            if ($ticketIds->isNotEmpty()) {
                $results['messages'] = TicketMessage::query()
                    ->whereIn('ticket_id', $ticketIds)
                    ->delete();

                $results['tickets'] = Ticket::query()
                    ->whereIn('id', $ticketIds)
                    ->delete();
            }
        }

        if (array_sum($results) > 0) {
            $this->audit->record('security.retention_purged', properties: $results);
        }

        return $results;
    }
}
