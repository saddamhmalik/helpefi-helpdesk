<?php

namespace App\Domains\Macros\Support;

use App\Domains\Tickets\Models\Ticket;
use App\Models\User;

class MacroPlaceholderResolver
{
    public function resolve(string $body, ?Ticket $ticket = null, ?User $agent = null): string
    {
        $replacements = [
            '{{agent.name}}' => $agent?->name ?? '',
            '{{agent.email}}' => $agent?->email ?? '',
            '{{ticket.number}}' => $ticket?->number ?? '',
            '{{ticket.subject}}' => $ticket?->subject ?? '',
            '{{contact.name}}' => $ticket?->contact?->name ?? '',
            '{{contact.email}}' => $ticket?->contact?->email ?? '',
            '{{organization.name}}' => $ticket?->contact?->organization?->name ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $body);
    }

    public function placeholders(): array
    {
        return [
            ['token' => '{{agent.name}}', 'label' => 'Agent name'],
            ['token' => '{{agent.email}}', 'label' => 'Agent email'],
            ['token' => '{{ticket.number}}', 'label' => 'Ticket number'],
            ['token' => '{{ticket.subject}}', 'label' => 'Ticket subject'],
            ['token' => '{{contact.name}}', 'label' => 'Requester name'],
            ['token' => '{{contact.email}}', 'label' => 'Requester email'],
            ['token' => '{{organization.name}}', 'label' => 'Organization name'],
        ];
    }
}
