<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Mail\MarketingContactInquiryMail;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class MarketingContactService
{
    public function topics(): array
    {
        return ['sales', 'support', 'partnership', 'enterprise', 'other'];
    }

    public function submit(array $data): void
    {
        $recipient = config('marketing_seo.organization.contact_email');

        if (! is_string($recipient) || $recipient === '') {
            throw new InvalidArgumentException('Marketing contact email is not configured.');
        }

        Mail::send(new MarketingContactInquiryMail(
            name: $data['name'],
            email: $data['email'],
            company: $data['company'] ?? null,
            topic: $data['topic'],
            message: $data['message'],
            recipient: $recipient,
        ));
    }
}
