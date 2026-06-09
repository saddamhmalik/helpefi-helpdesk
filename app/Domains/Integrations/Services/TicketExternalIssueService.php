<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Billing\Services\BillingService;
use App\Domains\Integrations\Models\IntegrationConnection;
use App\Domains\Integrations\Models\TicketExternalIssue;
use App\Domains\Integrations\Repositories\TicketExternalIssueRepository;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Domains\Tickets\Services\TicketService;
use InvalidArgumentException;

class TicketExternalIssueService
{
    public function __construct(
        private TicketExternalIssueRepository $issues,
        private TicketRepository $tickets,
        private TicketService $ticketService,
        private JiraIntegrationService $jira,
        private LinearIntegrationService $linear,
        private BillingService $billing,
    ) {
    }

    public function listForTicket(int $ticketId): array
    {
        return $this->issues->forTicket($ticketId)
            ->map(fn (TicketExternalIssue $issue) => $this->serialize($issue))
            ->values()
            ->all();
    }

    public function createIssue(int $ticketId, string $provider, int $userId): array
    {
        $this->billing->assertFeature('integrations');

        $ticket = $this->tickets->find($ticketId);
        $payload = match ($provider) {
            IntegrationConnection::PROVIDER_JIRA => $this->jira->createIssue($ticket),
            IntegrationConnection::PROVIDER_LINEAR => $this->linear->createIssue($ticket),
            default => throw new InvalidArgumentException('Unsupported provider.'),
        };

        $issue = $this->issues->create(array_merge($payload, [
            'ticket_id' => $ticketId,
            'provider' => $provider,
            'last_synced_at' => now(),
        ]));

        return $this->serialize($issue);
    }

    public function linkIssue(int $ticketId, string $provider, string $reference): array
    {
        $this->billing->assertFeature('integrations');

        $this->tickets->find($ticketId);

        $payload = match ($provider) {
            IntegrationConnection::PROVIDER_JIRA => $this->jira->fetchIssue($reference),
            IntegrationConnection::PROVIDER_LINEAR => $this->linear->fetchIssue($reference),
            default => throw new InvalidArgumentException('Unsupported provider.'),
        };

        if ($existing = $this->issues->findByExternal($provider, $payload['external_id'])) {
            if ($existing->ticket_id !== $ticketId) {
                throw new InvalidArgumentException('Issue is already linked to another ticket.');
            }

            return $this->serialize($this->issues->update($existing, array_merge($payload, [
                'last_synced_at' => now(),
            ])));
        }

        $issue = $this->issues->create(array_merge($payload, [
            'ticket_id' => $ticketId,
            'provider' => $provider,
            'last_synced_at' => now(),
        ]));

        return $this->serialize($issue);
    }

    public function unlinkIssue(int $ticketId, int $issueId): void
    {
        $issue = $this->issues->forTicket($ticketId)->firstWhere('id', $issueId);

        if (! $issue) {
            throw new InvalidArgumentException('Issue link not found.');
        }

        $this->issues->delete($issue);
    }

    public function syncOutbound(Ticket $ticket, array $context = []): void
    {
        if (($context['source'] ?? null) === 'external_integration') {
            return;
        }

        if (! in_array('ticket_status_id', $context['changed'] ?? [], true)) {
            return;
        }

        foreach ($this->issues->forTicket($ticket->id) as $issue) {
            try {
                match ($issue->provider) {
                    IntegrationConnection::PROVIDER_JIRA => $this->jira->pushTicketStatus($ticket, $issue),
                    IntegrationConnection::PROVIDER_LINEAR => $this->linear->pushTicketStatus($ticket, $issue),
                    default => null,
                };
            } catch (\Throwable) {
            }
        }
    }

    public function handleJiraWebhook(array $payload): void
    {
        $issueKey = (string) data_get($payload, 'issue.key', '');

        if (! $issueKey) {
            return;
        }

        $issue = $this->issues->findByExternal(IntegrationConnection::PROVIDER_JIRA, (string) data_get($payload, 'issue.id'))
            ?? $this->issues->findByKey(IntegrationConnection::PROVIDER_JIRA, $issueKey);

        if (! $issue) {
            return;
        }

        $status = (string) data_get($payload, 'issue.fields.status.name', '');
        $this->issues->update($issue, [
            'status' => $status,
            'last_synced_at' => now(),
        ]);

        $this->syncTicketFromExternalStatus($issue->ticket_id, $status);
    }

    public function handleLinearWebhook(array $payload): void
    {
        $issueId = (string) data_get($payload, 'data.id', '');

        if ($issueId === '') {
            return;
        }

        $issue = $this->issues->findByExternal(IntegrationConnection::PROVIDER_LINEAR, $issueId);

        if (! $issue) {
            return;
        }

        $status = (string) data_get($payload, 'data.state.name', '');
        $this->issues->update($issue, [
            'status' => $status,
            'last_synced_at' => now(),
        ]);

        $this->syncTicketFromExternalStatus($issue->ticket_id, $status);
    }

    private function syncTicketFromExternalStatus(int $ticketId, string $status): void
    {
        $closedNames = config('integrations.closed_status_names', []);
        $isClosed = in_array(strtolower($status), $closedNames, true);

        $ticket = $this->tickets->find($ticketId);
        $ticket->loadMissing('status:id,name,slug,is_closed');

        if ($isClosed && ! $ticket->status?->is_closed) {
            $closedStatusId = TicketStatus::query()->where('is_closed', true)->orderBy('sort_order')->value('id');

            if ($closedStatusId) {
                $this->ticketService->update($ticketId, [
                    'ticket_status_id' => $closedStatusId,
                ], null, ['source' => 'external_integration']);
            }

            return;
        }

        if (! $isClosed && $ticket->status?->is_closed) {
            $openStatusId = TicketStatus::query()->where('is_closed', false)->orderBy('sort_order')->value('id');

            if ($openStatusId) {
                $this->ticketService->update($ticketId, [
                    'ticket_status_id' => $openStatusId,
                ], null, ['source' => 'external_integration']);
            }
        }
    }

    private function serialize(TicketExternalIssue $issue): array
    {
        return [
            'id' => $issue->id,
            'provider' => $issue->provider,
            'external_id' => $issue->external_id,
            'external_key' => $issue->external_key,
            'external_url' => $issue->external_url,
            'status' => $issue->status,
            'last_synced_at' => $issue->last_synced_at?->toIso8601String(),
        ];
    }
}
