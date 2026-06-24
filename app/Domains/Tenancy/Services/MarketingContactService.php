<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Platform\Services\MarketingLeadService;
use App\Domains\Tenancy\Mail\MarketingContactInquiryMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class MarketingContactService
{
    public function __construct(private MarketingLeadService $leads) {}

    public function topics(): array
    {
        return ['sales', 'support', 'partnership', 'enterprise', 'other'];
    }

    public function submitRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:120'],
            'topic' => ['required', 'string', Rule::in($this->topics())],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'marketing_consent' => ['sometimes', 'boolean'],
            'website' => ['nullable', 'string', 'max:0'],
            'cf_turnstile_response' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function submit(array $data, Request $request): void
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

        $this->leads->captureFromContact($data, $request);
    }
}
