<?php

namespace App\Domains\Security\Services;

use App\Domains\Security\Repositories\AuditLogRepository;
use App\Support\CsvStream;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogExportService
{
    public function __construct(private AuditLogRepository $auditLogs)
    {
    }

    public function csv(array $filters): StreamedResponse
    {
        return CsvStream::download(CsvStream::timestampedFilename('audit-logs'), function ($handle) use ($filters) {
            fputcsv($handle, [
                'When',
                'Event',
                'Actor',
                'Actor email',
                'Subject type',
                'Subject id',
                'IP address',
                'Details',
            ]);

            $this->auditLogs->exportRows($filters, function ($log) use ($handle) {
                $subjectType = $log->subject_type
                    ? class_basename($log->subject_type)
                    : null;

                fputcsv($handle, [
                    $log->created_at?->toDateTimeString(),
                    $log->event,
                    $log->user?->name,
                    $log->user?->email ?? $log->actor_email,
                    $subjectType,
                    $log->subject_id,
                    $log->ip_address,
                    $log->properties ? json_encode($log->properties, JSON_UNESCAPED_UNICODE) : null,
                ]);
            });
        });
    }
}
