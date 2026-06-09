<?php

namespace App\Domains\Reports\Services;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Ai\Services\AiDeflectionService;
use App\Domains\Csat\Services\CsatService;
use App\Domains\Knowledge\Services\KbDeflectionService;
use App\Domains\Knowledge\Services\KnowledgeService;
use App\Domains\TimeTracking\Services\TimeTrackingService;
use App\Domains\Reports\Models\SavedReport;
use App\Domains\Reports\Repositories\ReportRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportService
{
    public function __construct(
        private ReportRepository $reports,
        private KnowledgeService $knowledge,
        private CsatService $csat,
        private AiDeflectionService $deflection,
        private KbDeflectionService $kbDeflection,
        private TimeTrackingService $timeTracking,
    ) {
    }

    public function dashboardWidgets(): array
    {
        $weekStart = now()->startOfWeek();

        return [
            'openTickets' => $this->reports->openTicketCount(),
            'contacts' => Contact::query()->count(),
            'publishedArticles' => $this->knowledge->publishedCount(),
            'createdThisWeek' => $this->reports->ticketsCreatedSince($weekStart),
            'resolvedThisWeek' => $this->reports->ticketsResolvedSince($weekStart),
            'slaBreaches' => $this->reports->activeSlaBreachCount(),
            'ticketStatuses' => $this->reports->countByStatus(),
            'ticketPriorities' => $this->reports->countByPriority(),
            'topAgents' => $this->reports->topAgentsByOpenTickets(),
            'volumeTrend' => $this->reports->ticketVolumeTrend(),
            'csat' => $this->csat->dashboardSummary(),
            'deflection' => $this->deflection->dashboardSummary(),
            'kbDeflection' => $this->kbDeflection->dashboardSummary(),
        ];
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
            SavedReport::TYPE_TICKETS => [
                'reportName' => $reportName,
                'typeLabel' => $this->typeLabel($type),
                'generatedAt' => now()->toDayDateTimeString(),
                'format' => 'table',
                'headers' => ['Number', 'Subject', 'Contact', 'Status', 'Priority', 'Assignee', 'Created', 'Closed'],
                'rows' => $this->reports->ticketsReportRows($filters)->map(fn ($ticket) => [
                    $ticket->number,
                    $ticket->subject,
                    $ticket->contact?->name,
                    $ticket->status?->name,
                    $ticket->priority?->name,
                    $ticket->assignee?->name,
                    $ticket->created_at?->toDateTimeString(),
                    $ticket->closed_at?->toDateTimeString(),
                ])->all(),
            ],
            SavedReport::TYPE_SLA_BREACHES => [
                'reportName' => $reportName,
                'typeLabel' => $this->typeLabel($type),
                'generatedAt' => now()->toDayDateTimeString(),
                'format' => 'table',
                'headers' => ['Number', 'Subject', 'Assignee', 'First response breached', 'Resolution breached', 'Created'],
                'rows' => $this->reports->slaBreachesReportRows($filters)->map(fn ($ticket) => [
                    $ticket->number,
                    $ticket->subject,
                    $ticket->assignee?->name,
                    $ticket->slaTimer?->first_response_breached ? 'Yes' : 'No',
                    $ticket->slaTimer?->resolution_breached ? 'Yes' : 'No',
                    $ticket->created_at?->toDateTimeString(),
                ])->all(),
            ],
            SavedReport::TYPE_AGENT_PERFORMANCE => [
                'reportName' => $reportName,
                'typeLabel' => $this->typeLabel($type),
                'generatedAt' => now()->toDayDateTimeString(),
                'format' => 'table',
                'headers' => ['Agent', 'Open tickets', 'Closed tickets', 'Total tickets'],
                'rows' => $this->reports->agentPerformanceReport($filters)->map(fn ($row) => [
                    $row['agent_name'],
                    $row['open_count'],
                    $row['closed_count'],
                    $row['total_count'],
                ])->all(),
            ],
            SavedReport::TYPE_CSAT => [
                'reportName' => $reportName,
                'typeLabel' => $this->typeLabel($type),
                'generatedAt' => now()->toDayDateTimeString(),
                'format' => 'table',
                'headers' => ['Ticket', 'Subject', 'Contact', 'Rating', 'Comment', 'Channel', 'Assignee', 'Submitted'],
                'rows' => collect($this->csat->exportRows($filters)['rows'])->map(fn ($response) => [
                    $response->ticket?->number,
                    $response->ticket?->subject,
                    $response->contact?->name,
                    $response->rating,
                    $response->comment,
                    $response->channel,
                    $response->ticket?->assignee?->name,
                    $response->created_at?->toDateTimeString(),
                ])->all(),
            ],
            SavedReport::TYPE_TIME_TRACKING => $this->timeTrackingPdfViewData($reportName, $type, $filters),
        };
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
