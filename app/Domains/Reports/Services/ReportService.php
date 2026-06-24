<?php

namespace App\Domains\Reports\Services;

use App\Domains\Ai\Services\AiDeflectionService;
use App\Domains\Csat\Services\CsatService;
use App\Domains\Knowledge\Services\KbDeflectionService;
use App\Domains\TimeTracking\Services\TimeTrackingService;
use App\Domains\Reports\Models\SavedReport;
use App\Domains\Reports\Support\DashboardWidgetCache;
use App\Domains\Reports\Repositories\ReportRepository;
use App\Support\TenantCache;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportService
{
    private const PDF_MAX_ROWS = 500;

    public function __construct(
        private ReportRepository $reports,
        private CsatService $csat,
        private AiDeflectionService $deflection,
        private KbDeflectionService $kbDeflection,
        private TimeTrackingService $timeTracking,
    ) {
    }

    public function dashboardWidgets(): array
    {
        if (! tenancy()->initialized) {
            return $this->loadDashboardWidgets();
        }

        return DashboardWidgetCache::remember(300, fn () => $this->loadDashboardWidgets());
    }

    private function loadDashboardWidgets(): array
    {
        $weekStart = now()->startOfWeek();
        $snapshot = $this->reports->dashboardSnapshot($weekStart);
        $meta = $this->reports->dashboardMetaCounts();

        return [
            'openTickets' => $snapshot['open_tickets'],
            'contacts' => $meta['contacts'],
            'publishedArticles' => $meta['published_articles'],
            'createdThisWeek' => $snapshot['created_since'],
            'resolvedThisWeek' => $snapshot['resolved_since'],
            'slaBreaches' => $snapshot['sla_breaches'],
            'ticketStatuses' => $this->dashboardTicketStatuses($snapshot['ticket_statuses']),
            'ticketPriorities' => $this->dashboardTicketPriorities($snapshot['ticket_priorities']),
            'topAgents' => $this->dashboardTopAgents($snapshot['top_agents']),
            'volumeTrend' => $snapshot['volume_trend'],
            'csat' => $this->csat->dashboardSummary(),
            'deflection' => $this->deflection->dashboardSummary(),
            'kbDeflection' => $this->kbDeflection->dashboardSummary(),
        ];
    }

    private function dashboardTicketStatuses(mixed $statuses): array
    {
        return collect($statuses)
            ->values()
            ->map(fn ($status) => [
                'id' => $status->id,
                'name' => $status->name,
                'slug' => $status->slug,
                'color' => $status->color,
                'tickets_count' => (int) $status->tickets_count,
            ])
            ->all();
    }

    private function dashboardTicketPriorities(mixed $priorities): array
    {
        return collect($priorities)
            ->values()
            ->map(fn ($priority) => [
                'id' => $priority->id,
                'name' => $priority->name,
                'slug' => $priority->slug,
                'tickets_count' => (int) $priority->tickets_count,
            ])
            ->all();
    }

    private function dashboardTopAgents(mixed $agents): array
    {
        return collect($agents)->values()->all();
    }

    public function savedForUser(int $userId): Collection
    {
        return $this->reports->savedForUser($userId);
    }

    public function generateCsvContent(string $type, array $filters): string
    {
        $this->assertValidType($type);

        $handle = fopen('php://temp', 'r+');

        match ($type) {
            SavedReport::TYPE_TICKETS => $this->streamTicketsCsv($handle, $filters),
            SavedReport::TYPE_SLA_BREACHES => $this->streamSlaBreachesCsv($handle, $filters),
            SavedReport::TYPE_AGENT_PERFORMANCE => $this->streamAgentPerformanceCsv($handle, $filters),
            SavedReport::TYPE_CSAT => $this->streamCsatCsv($handle, $filters),
            SavedReport::TYPE_TIME_TRACKING => $this->streamTimeTrackingCsv($handle, $filters),
        };

        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $content;
    }

    public function generatePdfContent(string $type, array $filters, string $reportName): string
    {
        $this->assertValidType($type);

        return Pdf::loadView('reports.export-pdf', $this->pdfViewData($type, $filters, $reportName))
            ->setPaper('a4', 'landscape')
            ->output();
    }

    public function typeLabel(string $type): string
    {
        return collect($this->types())->firstWhere('value', $type)['label'] ?? $type;
    }

    public function createSaved(int $userId, string $name, string $type, array $filters, bool $isDefault = false): SavedReport
    {
        $this->assertValidType($type);

        return $this->reports->createSaved($userId, $name, $type, $filters, $isDefault);
    }

    public function deleteSaved(int $id, int $userId): void
    {
        $this->reports->deleteSaved($this->reports->findSavedForUser($id, $userId));
    }

    public function run(string $type, array $filters, int $perPage = 50): array
    {
        $this->assertValidType($type);

        return match ($type) {
            SavedReport::TYPE_TICKETS => [
                'type' => $type,
                'rows' => $this->reports->ticketsReport($filters, $perPage),
                'format' => 'tickets',
            ],
            SavedReport::TYPE_SLA_BREACHES => [
                'type' => $type,
                'rows' => $this->reports->slaBreachesReport($filters, $perPage),
                'format' => 'tickets',
            ],
            SavedReport::TYPE_AGENT_PERFORMANCE => [
                'type' => $type,
                'rows' => $this->reports->agentPerformanceReport($filters),
                'format' => 'agents',
            ],
            SavedReport::TYPE_CSAT => $this->csat->report($filters, $perPage),
            SavedReport::TYPE_TIME_TRACKING => $this->timeTracking->report($filters),
        };
    }

    public function exportCsv(string $type, array $filters): StreamedResponse
    {
        $this->assertValidType($type);

        $filename = "{$type}-report-".now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($type, $filters) {
            $handle = fopen('php://output', 'w');

            match ($type) {
                SavedReport::TYPE_TICKETS => $this->streamTicketsCsv($handle, $filters),
                SavedReport::TYPE_SLA_BREACHES => $this->streamSlaBreachesCsv($handle, $filters),
                SavedReport::TYPE_AGENT_PERFORMANCE => $this->streamAgentPerformanceCsv($handle, $filters),
                SavedReport::TYPE_CSAT => $this->streamCsatCsv($handle, $filters),
                SavedReport::TYPE_TIME_TRACKING => $this->streamTimeTrackingCsv($handle, $filters),
            };

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function types(): array
    {
        return [
            ['value' => SavedReport::TYPE_TICKETS, 'label' => 'Tickets'],
            ['value' => SavedReport::TYPE_SLA_BREACHES, 'label' => 'SLA breaches'],
            ['value' => SavedReport::TYPE_AGENT_PERFORMANCE, 'label' => 'Agent performance'],
            ['value' => SavedReport::TYPE_CSAT, 'label' => 'CSAT satisfaction'],
            ['value' => SavedReport::TYPE_TIME_TRACKING, 'label' => 'Time tracking'],
        ];
    }

    private function streamTicketsCsv($handle, array $filters): void
    {
        fputcsv($handle, ['Number', 'Subject', 'Contact', 'Status', 'Priority', 'Assignee', 'Created', 'Closed']);

        foreach ($this->reports->ticketsReportRows($filters) as $ticket) {
            fputcsv($handle, [
                $ticket->number,
                $ticket->subject,
                $ticket->contact?->name,
                $ticket->status?->name,
                $ticket->priority?->name,
                $ticket->assignee?->name,
                $ticket->created_at?->toDateTimeString(),
                $ticket->closed_at?->toDateTimeString(),
            ]);
        }
    }

    private function streamSlaBreachesCsv($handle, array $filters): void
    {
        fputcsv($handle, ['Number', 'Subject', 'Assignee', 'First response breached', 'Resolution breached', 'Created']);

        foreach ($this->reports->slaBreachesReportRows($filters) as $ticket) {
            fputcsv($handle, [
                $ticket->number,
                $ticket->subject,
                $ticket->assignee?->name,
                $ticket->slaTimer?->first_response_breached ? 'Yes' : 'No',
                $ticket->slaTimer?->resolution_breached ? 'Yes' : 'No',
                $ticket->created_at?->toDateTimeString(),
            ]);
        }
    }

    private function streamAgentPerformanceCsv($handle, array $filters): void
    {
        fputcsv($handle, ['Agent', 'Open tickets', 'Closed tickets', 'Total tickets']);

        foreach ($this->reports->agentPerformanceReport($filters) as $row) {
            fputcsv($handle, [
                $row['agent_name'],
                $row['open_count'],
                $row['closed_count'],
                $row['total_count'],
            ]);
        }
    }

    private function streamCsatCsv($handle, array $filters): void
    {
        fputcsv($handle, ['Ticket', 'Subject', 'Contact', 'Rating', 'Comment', 'Channel', 'Assignee', 'Submitted']);

        foreach ($this->csat->exportRows($filters)['rows'] as $response) {
            fputcsv($handle, [
                $response->ticket?->number,
                $response->ticket?->subject,
                $response->contact?->name,
                $response->rating,
                $response->comment,
                $response->channel,
                $response->ticket?->assignee?->name,
                $response->created_at?->toDateTimeString(),
            ]);
        }
    }

    private function streamTimeTrackingCsv($handle, array $filters): void
    {
        $report = $this->timeTracking->report($filters);

        fputcsv($handle, ['Agent', 'Total minutes', 'Entries']);

        foreach ($report['agents'] as $row) {
            fputcsv($handle, [
                $row['agent_name'],
                $row['total_minutes'],
                $row['entry_count'],
            ]);
        }

        fputcsv($handle, []);
        fputcsv($handle, ['Team', 'Total minutes', 'Entries']);

        foreach ($report['teams'] as $row) {
            fputcsv($handle, [
                $row['team_name'],
                $row['total_minutes'],
                $row['entry_count'],
            ]);
        }
    }

    private function assertValidType(string $type): void
    {
        if (! in_array($type, [
            SavedReport::TYPE_TICKETS,
            SavedReport::TYPE_SLA_BREACHES,
            SavedReport::TYPE_AGENT_PERFORMANCE,
            SavedReport::TYPE_CSAT,
            SavedReport::TYPE_TIME_TRACKING,
        ], true)) {
            throw new InvalidArgumentException('Invalid report type.');
        }
    }

    private function pdfViewData(string $type, array $filters, string $reportName): array
    {
        return match ($type) {
            SavedReport::TYPE_TICKETS => $this->pdfTableData(
                $reportName,
                $type,
                ['Number', 'Subject', 'Contact', 'Status', 'Priority', 'Assignee', 'Created', 'Closed'],
                $this->reports->ticketsReportRows($filters),
                fn ($ticket) => [
                    $ticket->number,
                    $ticket->subject,
                    $ticket->contact?->name,
                    $ticket->status?->name,
                    $ticket->priority?->name,
                    $ticket->assignee?->name,
                    $ticket->created_at?->toDateTimeString(),
                    $ticket->closed_at?->toDateTimeString(),
                ],
            ),
            SavedReport::TYPE_SLA_BREACHES => $this->pdfTableData(
                $reportName,
                $type,
                ['Number', 'Subject', 'Assignee', 'First response breached', 'Resolution breached', 'Created'],
                $this->reports->slaBreachesReportRows($filters),
                fn ($ticket) => [
                    $ticket->number,
                    $ticket->subject,
                    $ticket->assignee?->name,
                    $ticket->slaTimer?->first_response_breached ? 'Yes' : 'No',
                    $ticket->slaTimer?->resolution_breached ? 'Yes' : 'No',
                    $ticket->created_at?->toDateTimeString(),
                ],
            ),
            SavedReport::TYPE_AGENT_PERFORMANCE => (function () use ($reportName, $type, $filters) {
                $agents = $this->reports->agentPerformanceReport($filters);

                return [
                    'reportName' => $reportName,
                    'typeLabel' => $this->typeLabel($type),
                    'generatedAt' => now()->toDayDateTimeString(),
                    'format' => 'table',
                    'headers' => ['Agent', 'Open tickets', 'Closed tickets', 'Total tickets'],
                    'rows' => $agents->take(self::PDF_MAX_ROWS)->map(fn ($row) => [
                        $row['agent_name'],
                        $row['open_count'],
                        $row['closed_count'],
                        $row['total_count'],
                    ])->all(),
                    'truncated' => $agents->count() > self::PDF_MAX_ROWS,
                ];
            })(),
            SavedReport::TYPE_CSAT => $this->pdfTableData(
                $reportName,
                $type,
                ['Ticket', 'Subject', 'Contact', 'Rating', 'Comment', 'Channel', 'Assignee', 'Submitted'],
                collect($this->csat->exportRows($filters)['rows']),
                fn ($response) => [
                    $response->ticket?->number,
                    $response->ticket?->subject,
                    $response->contact?->name,
                    $response->rating,
                    $response->comment,
                    $response->channel,
                    $response->ticket?->assignee?->name,
                    $response->created_at?->toDateTimeString(),
                ],
            ),
            SavedReport::TYPE_TIME_TRACKING => $this->timeTrackingPdfViewData($reportName, $type, $filters),
        };
    }

    private function pdfTableData(string $reportName, string $type, array $headers, iterable $source, callable $mapper): array
    {
        $rows = [];
        $truncated = false;
        $count = 0;

        foreach ($source as $item) {
            $count++;

            if ($count > self::PDF_MAX_ROWS) {
                $truncated = true;
                break;
            }

            $rows[] = $mapper($item);
        }

        return [
            'reportName' => $reportName,
            'typeLabel' => $this->typeLabel($type),
            'generatedAt' => now()->toDayDateTimeString(),
            'format' => 'table',
            'headers' => $headers,
            'rows' => $rows,
            'truncated' => $truncated,
        ];
    }

    private function timeTrackingPdfViewData(string $reportName, string $type, array $filters): array
    {
        $report = $this->timeTracking->report($filters);

        return [
            'reportName' => $reportName,
            'typeLabel' => $this->typeLabel($type),
            'generatedAt' => now()->toDayDateTimeString(),
            'format' => 'sections',
            'sections' => [
                [
                    'title' => 'By agent',
                    'headers' => ['Agent', 'Total minutes', 'Entries'],
                    'rows' => collect($report['agents'])->map(fn ($row) => [
                        $row['agent_name'],
                        $row['total_minutes'],
                        $row['entry_count'],
                    ])->all(),
                ],
                [
                    'title' => 'By team',
                    'headers' => ['Team', 'Total minutes', 'Entries'],
                    'rows' => collect($report['teams'])->map(fn ($row) => [
                        $row['team_name'],
                        $row['total_minutes'],
                        $row['entry_count'],
                    ])->all(),
                ],
            ],
        ];
    }
}
