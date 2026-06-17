<?php

namespace App\Domains\Tenancy\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MarketingContactInquiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private string $name,
        private string $email,
        private ?string $company,
        private string $topic,
        private string $message,
        private string $recipient,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->email, $this->name)],
            to: [new Address($this->recipient)],
            subject: '['.config('app.name', 'Helpefi').' Contact] '.$this->topicLabel().' — '.$this->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.marketing-contact-inquiry',
            with: [
                'name' => $this->name,
                'email' => $this->email,
                'company' => $this->company,
                'topicLabel' => $this->topicLabel(),
                'inquiryMessage' => $this->message,
                'appName' => config('app.name', 'Helpefi'),
            ],
        );
    }

    private function topicLabel(): string
    {
        return match ($this->topic) {
            'sales' => 'Sales & demo',
            'support' => 'Product support',
            'partnership' => 'Partnership',
            'enterprise' => 'Enterprise',
            default => 'General inquiry',
        };
    }
}
