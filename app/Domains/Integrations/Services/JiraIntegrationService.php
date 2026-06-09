<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Models\IntegrationConnection;
use App\Domains\Integrations\Models\TicketExternalIssue;
use App\Domains\Integrations\Repositories\IntegrationConnectionRepository;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

class JiraIntegrationService
{
    public function __construct(private IntegrationConnectionRepository $connections)
    {
    }

    public function createIssue(Ticket $ticket): array
    {
        $config = $this->config();
        $ticket->loadMissing(['status:id,name,slug', 'priority:id,name,slug']);

        $response = Http::withBasicAuth($config['email'], $config['api_token'])
            ->acceptJson()
            ->post($this->apiUrl($config, 'issue'), [
                'fields' => [
                    'project' => ['key' => $config['project_key']],
                    'summary' => "[{$ticket->number}] {$ticket->subject}",
                    'description' => $this->issueDescription($ticket),
                    'issuetype' => ['name' => $config['issue_type'] ?? 'Task'],
                ],
            ])
            ->throw();

        $body = $response->json();

        return [
            'external_id' => (string) $body['id'],
            'external_key' => (string) $body['key'],
            'external_url' => rtrim($config['site_url'], '/').'/browse/'.$body['key'],
            'status' => 'To Do',
        ];
    }

    public function fetchIssue(string $issueKey): array
    {
        $config = $this->config();

        $response = Http::withBasicAuth($config['email'], $config['api_token'])
            ->acceptJson()
            ->get($this->apiUrl($config, 'issue/'.$issueKey))
            ->throw();

        $issue = $response->json();

        return [
            'external_id' => (string) $issue['id'],
            'external_key' => (string) $issue['key'],
            'external_url' => rtrim($config['site_url'], '/').'/browse/'.$issue['key'],
            'status' => (string) ($issue['fields']['status']['name'] ?? ''),
        ];
    }

    public function pushTicketStatus(Ticket $ticket, TicketExternalIssue $issue): void
    {
        $config = $this->config();
        $ticket->loadMissing('status:id,name,slug,is_closed');

        $target = $ticket->status?->is_closed ? ($config['done_transition'] ?? 'Done') : ($config['reopen_transition'] ?? 'To Do');

        $transitions = Http::withBasicAuth($config['email'], $config['api_token'])
            ->acceptJson()
            ->get($this->apiUrl($config, 'issue/'.$issue->external_key.'/transitions'))
            ->throw()
            ->json('transitions') ?? [];

        $transition = collect($transitions)->first(
            fn (array $item) => strcasecmp($item['name'], $target) === 0
                || strcasecmp($item['to']['name'] ?? '', $target) === 0,
        );

        if (! $transition) {
            return;
        }

        Http::withBasicAuth($config['email'], $config['api_token'])
            ->acceptJson()
            ->post($this->apiUrl($config, 'issue/'.$issue->external_key.'/transitions'), [
                'transition' => ['id' => $transition['id']],
            ])
            ->throw();
    }

    public function inboundStatus(string $issueKey): ?string
    {
        return $this->fetchIssue($issueKey)['status'] ?? null;
    }

    public function verifySecret(?string $secret): bool
    {
        $connection = $this->connections->activeForProvider(IntegrationConnection::PROVIDER_JIRA);

        if (! $connection) {
            return false;
        }

        $expected = $connection->config['webhook_secret'] ?? null;

        return $expected && hash_equals($expected, (string) $secret);
    }

    private function config(): array
    {
        $connection = $this->connections->activeForProvider(IntegrationConnection::PROVIDER_JIRA);

        if (! $connection) {
            throw new InvalidArgumentException('Jira integration is not configured.');
        }

        $config = $connection->config ?? [];
        $required = ['site_url', 'email', 'api_token', 'project_key'];

        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new InvalidArgumentException("Jira setting missing: {$key}");
            }
        }

        return $config;
    }

    private function apiUrl(array $config, string $path): string
    {
        return rtrim($config['site_url'], '/').'/rest/api/3/'.ltrim($path, '/');
    }

    private function issueDescription(Ticket $ticket): array
    {
        $url = url("/tickets/{$ticket->id}");
        $body = strip_tags((string) ($ticket->description ?: 'No description provided.'));

        return [
            'type' => 'doc',
            'version' => 1,
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => Str::limit($body, 3000)],
                    ],
                ],
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Helpdesk ticket: '.$url],
                    ],
                ],
            ],
        ];
    }
}
