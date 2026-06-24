<?php

namespace Tests\Unit\Tickets;

use App\Domains\Tickets\Services\TicketPeopleFieldResolver;
use App\Domains\Tickets\Support\MessageBodySanitizer;
use PHPUnit\Framework\TestCase;

class TicketPeopleFieldResolverTest extends TestCase
{
    public function test_extract_people_fields_removes_keys_from_source_array(): void
    {
        $data = [
            'subject' => 'Help',
            'cc_emails' => ['a@example.com'],
            'requester_email' => ' user@example.com ',
            'requester_name' => ' User ',
        ];

        [$ccEmails, $email, $name] = (new TicketPeopleFieldResolver(
            $this->createStub(\App\Domains\Contacts\Services\ContactService::class),
            $this->createStub(\App\Domains\Settings\Services\HelpdeskSettingService::class),
            $this->createStub(\App\Domains\Workforce\Services\WorkforceService::class),
        ))->extractPeopleFields($data);

        $this->assertSame(['a@example.com'], $ccEmails);
        $this->assertSame('user@example.com', $email);
        $this->assertSame('User', $name);
        $this->assertArrayNotHasKey('cc_emails', $data);
        $this->assertArrayNotHasKey('requester_email', $data);
        $this->assertArrayNotHasKey('requester_name', $data);
        $this->assertSame('Help', $data['subject']);
    }

    public function test_normalize_rich_text_returns_null_for_blank_content(): void
    {
        $resolver = new TicketPeopleFieldResolver(
            $this->createStub(\App\Domains\Contacts\Services\ContactService::class),
            $this->createStub(\App\Domains\Settings\Services\HelpdeskSettingService::class),
            $this->createStub(\App\Domains\Workforce\Services\WorkforceService::class),
        );

        $this->assertNull($resolver->normalizeRichText(null));
        $this->assertNull($resolver->normalizeRichText('<p></p>'));
        $this->assertSame('<p>Hello</p>', $resolver->normalizeRichText('<p>Hello</p>'));
        $this->assertFalse(MessageBodySanitizer::isEmpty($resolver->normalizeRichText('<p>Hello</p>')));
    }
}
