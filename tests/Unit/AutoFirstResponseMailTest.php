<?php

namespace Tests\Unit;

use App\Domains\Channels\Mail\AutoFirstResponseMail;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use Tests\TestCase;

class AutoFirstResponseMailTest extends TestCase
{
    public function test_mail_includes_customer_original_message(): void
    {
        $ticket = new Ticket([
            'number' => 'HD-100',
            'subject' => 'Need help',
        ]);

        $autoMessage = new TicketMessage([
            'body' => '<p>Thanks, we received your request.</p>',
        ]);

        $customerMessage = new TicketMessage([
            'body' => 'Please reset my password.',
        ]);

        $mailable = new AutoFirstResponseMail(
            $ticket,
            $autoMessage,
            $customerMessage,
            'support@example.com',
            'Support',
            '<support@example.com>',
        );

        $rendered = $mailable->render();

        $this->assertStringContainsString('Thanks, we received your request.', $rendered);
        $this->assertStringContainsString('Please reset my password.', $rendered);
        $this->assertStringContainsString('Your message:', $rendered);
    }
}
