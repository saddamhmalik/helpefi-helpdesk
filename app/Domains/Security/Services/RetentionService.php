<?php

namespace App\Domains\Security\Services;

use App\Domains\Security\Repositories\AuditLogRepository;
use App\Domains\Security\Repositories\SecuritySettingRepository;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketAttachment;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Services\TicketAttachmentService;
use App\Domains\Tickets\Services\TicketStatusLookup;

class RetentionService
{
    public function __construct(
        private SecuritySettingRepository $settings,
        private AuditLogRepository $auditLogs,
        private AuditLogService $audit,
        private TicketStatusLookup $statusLookup,
        private TicketAttachmentService $attachments,
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
            $closedStatusIds = $this->statusLookup->closedIds();
            $cutoff = now()->subDays($setting->closed_ticket_retention_days);

            $ticketIds = Ticket::query()
                ->whereIn('ticket_status_id', $closedStatusIds)
                ->where('closed_at', '<', $cutoff)
                ->pluck('id');

            if ($ticketIds->isNotEmpty()) {
                $attachmentRows = TicketAttachment::query()
                    ->whereIn('ticket_id', $ticketIds)
                    ->get(['path', 'storage_disk']);

                $this->attachments->deleteStored($attachmentRows);

                TicketAttachment::query()
                    ->whereIn('ticket_id', $ticketIds)
                    ->delete();

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
