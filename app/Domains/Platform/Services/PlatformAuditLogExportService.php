<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Repositories\PlatformAuditLogRepository;
use App\Support\CsvStream;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformAuditLogExportService
{
    public function __construct(private PlatformAuditLogRepository $logs)
    {
    }

    public function csv(array $filters): StreamedResponse
    {
        return CsvStream::download(CsvStream::timestampedFilename('platform-audit-logs'), function ($handle) use ($filters) {
            fputcsv($handle, [
                'When',
                'Event',
                'Actor',
                'Actor email',
                'Workspace',
                'Subject type',
                'Subject id',
                'IP address',
                'Details',
            ]);

            $this->logs->exportRows($filters, function ($log) use ($handle) {
                $subjectType = $log->subject_type
                    ? class_basename($log->subject_type)
                    : null;

                fputcsv($handle, [
                    $log->created_at?->toDateTimeString(),
                    $log->event,
                    $log->user?->name,
                    $log->user?->email ?? $log->actor_email,
                    $log->tenant?->name ?? $log->tenant_id,
                    $subjectType,
                    $log->subject_id,
                    $log->ip_address,
                    $log->properties ? json_encode($log->properties, JSON_UNESCAPED_UNICODE) : null,
                ]);
            });
        });
    }
}
