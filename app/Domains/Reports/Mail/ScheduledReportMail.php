<?php

namespace App\Domains\Reports\Mail;

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
        return new Envelope(
            subject: "Scheduled report: {$this->report->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.scheduled-report',
            with: [
                'recipient' => $this->recipient,
                'report' => $this->report,
                'schedule' => $this->schedule,
                'typeLabel' => match ($this->report->type) {
                    SavedReport::TYPE_TICKETS => 'Tickets',
                    SavedReport::TYPE_SLA_BREACHES => 'SLA breaches',
                    SavedReport::TYPE_AGENT_PERFORMANCE => 'Agent performance',
                    SavedReport::TYPE_CSAT => 'CSAT satisfaction',
                    SavedReport::TYPE_TIME_TRACKING => 'Time tracking',
                    default => $this->report->type,
                },
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->attachmentContent, $this->attachmentFilename)
                ->withMime($this->attachmentMime),
        ];
    }
}
