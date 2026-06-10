<?php

namespace App\Domains\Reports\Mail;

use App\Domains\Channels\Models\EmailTemplate;
use App\Domains\Channels\Services\EmailTemplateService;
use App\Domains\Reports\Models\ReportSchedule;
use App\Domains\Reports\Models\SavedReport;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private User $recipient,
        private SavedReport $report,
        private ReportSchedule $schedule,
        private string $attachmentContent,
        private string $attachmentFilename,
        private string $attachmentMime,
    ) {
    }

    public function envelope(): Envelope
    {
        $typeLabel = $this->typeLabel();

        return new Envelope(
            subject: app(EmailTemplateService::class)->renderSubject(
                EmailTemplate::SLUG_SCHEDULED_REPORT,
                [
                    'recipient_name' => $this->recipient->name,
                    'report_name' => $this->report->name,
                    'report_type' => $typeLabel,
                ],
                "Scheduled report: {$this->report->name}",
            ),
        );
    }

    public function content(): Content
    {
        $typeLabel = $this->typeLabel();
        $rendered = app(EmailTemplateService::class)->render(
            EmailTemplate::SLUG_SCHEDULED_REPORT,
            [
                'recipient_name' => $this->recipient->name,
                'report_name' => $this->report->name,
                'report_type' => $typeLabel,
            ],
        );

        if ($rendered !== null) {
            return new Content(
                htmlString: app(EmailTemplateService::class)->wrapHtml($rendered['body_html']),
            );
        }

        return new Content(
            text: 'mail.scheduled-report',
            with: [
                'recipient' => $this->recipient,
                'report' => $this->report,
                'schedule' => $this->schedule,
                'typeLabel' => $typeLabel,
            ],
        );
    }

    private function typeLabel(): string
    {
        return match ($this->report->type) {
            SavedReport::TYPE_TICKETS => 'Tickets',
            SavedReport::TYPE_SLA_BREACHES => 'SLA breaches',
            SavedReport::TYPE_AGENT_PERFORMANCE => 'Agent performance',
            SavedReport::TYPE_CSAT => 'CSAT satisfaction',
            SavedReport::TYPE_TIME_TRACKING => 'Time tracking',
            default => $this->report->type,
        };
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->attachmentContent, $this->attachmentFilename)
                ->withMime($this->attachmentMime),
        ];
    }
}
