<?php

namespace App\Domains\Platform\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantInfrastructureFailureMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientEmail,
        public string $workspaceName,
        public string $workspaceSlug,
        public string $message,
        public string $source,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->recipientEmail],
            subject: 'Workspace infrastructure alert: '.$this->workspaceSlug,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>A workspace infrastructure check failed.</p>'
                .'<p><strong>Workspace:</strong> '.e($this->workspaceName).' ('.e($this->workspaceSlug).')</p>'
                .'<p><strong>Source:</strong> '.e($this->source).'</p>'
                .'<p><strong>Details:</strong> '.e($this->message).'</p>',
        );
    }
}
