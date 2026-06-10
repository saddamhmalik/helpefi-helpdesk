<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Models\IntegrationConnection;
use App\Domains\Integrations\Models\TicketExternalIssue;
use App\Domains\Integrations\Repositories\IntegrationConnectionRepository;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LinearIntegrationService
{
    public function __construct(private IntegrationConnectionRepository $connections)
    {
    }

    public function createIssue(Ticket $ticket): array
    {
        $config = $this->config();
        $ticket->loadMissing(['status:id,name,slug', 'priority:id,name,slug']);

        $response = $this->graphql($config, '
            mutation IssueCreate($input: IssueCreateInput!) {
                issueCreate(input: $input) {
                    success
                    issue {
                        id
                        identifier
                        url
                        state { name }
                    }
                }
            }
        ', [
            'input' => [
                'teamId' => $config['team_id'],
                'title' => "[{$ticket->number}] {$ticket->subject}",
                'description' => $this->issueDescription($ticket),
            ],
        ]);

        $issue = $response['data']['issueCreate']['issue'] ?? null;

        if (! $issue) {
            throw new InvalidArgumentException('Linear did not return a created issue.');
        }

        return [
            'external_id' => (string) $issue['id'],
            'external_key' => (string) $issue['identifier'],
            'external_url' => (string) $issue['url'],
            'status' => (string) ($issue['state']['name'] ?? ''),
        ];
    }

    public function fetchIssue(string $identifier): array
    {
        $config = $this->config();

        $response = $this->graphql($config, '
            query Issue($id: String!) {
                issue(id: $id) {
                    id
                    identifier
                    url
                    state { name }
                }
            }
        ', ['id' => $identifier]);

        $issue = $response['data']['issue'] ?? null;

        if (! $issue) {
            $response = $this->graphql($config, '
                query IssueByIdentifier($teamId: String!, $number: Float!) {
                    issues(filter: { team: { id: { eq: $teamId } }, number: { eq: $number } }) {
                        nodes {
                            id
                            identifier
                            url
                            state { name }
                        }
                    }
                }
            ', [
                'teamId' => $config['team_id'],
                'number' => (float) preg_replace('/\D+/', '', $identifier),
            ]);

            $issue = $response['data']['issues']['nodes'][0] ?? null;
        }

        if (! $issue) {
            throw new InvalidArgumentException('Linear issue not found.');
        }

        return [
            'external_id' => (string) $issue['id'],
            'external_key' => (string) $issue['identifier'],
            'external_url' => (string) $issue['url'],
            'status' => (string) ($issue['state']['name'] ?? ''),
        ];
    }

    public function pushTicketStatus(Ticket $ticket, TicketExternalIssue $issue): void
    {
        $config = $this->config();
        $ticket->loadMissing('status:id,name,slug,is_closed');

        $target = $ticket->status?->is_closed
            ? ($config['done_state'] ?? 'Done')
            : ($config['open_state'] ?? 'Todo');

        $states = $this->graphql($config, '
            query WorkflowStates($teamId: String!) {
                workflowStates(filter: { team: { id: { eq: $teamId } } }) {
                    nodes { id name type }
                }
            }
        ', ['teamId' => $config['team_id']]);

        $state = collect($states['data']['workflowStates']['nodes'] ?? [])
            ->first(fn (array $item) => strcasecmp($item['name'], $target) === 0);

        if (! $state) {
            return;
        }

        $this->graphql($config, '
            mutation IssueUpdate($id: String!, $stateId: String!) {
                issueUpdate(id: $id, input: { stateId: $stateId }) {
                    success
                }
            }
        ', [
            'id' => $issue->external_id,
            'stateId' => $state['id'],
        ]);
    }

    public function verifySignature(string $payload, ?string $signature): bool
    {
        $connection = $this->connections->activeForProvider(IntegrationConnection::PROVIDER_LINEAR);
        $secret = $connection?->config['webhook_secret'] ?? null;

        if (! $secret || ! $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    private function config(): array
    {
        $connection = $this->connections->activeForProvider(IntegrationConnection::PROVIDER_LINEAR);

        if (! $connection) {
            throw new InvalidArgumentException('Linear integration is not configured.');
        }

        $config = $connection->config ?? [];
        $required = ['api_key', 'team_id'];

        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new InvalidArgumentException("Linear setting missing: {$key}");
            }
        }

        return $config;
    }

    private function graphql(array $config, string $query, array $variables = []): array
    {
        return Http::withToken($config['api_key'])
            ->acceptJson()
            ->post('https://api.linear.app/graphql', [
                'query' => $query,
                'variables' => $variables,
            ])
            ->throw()
            ->json();
    }

    private function issueDescription(Ticket $ticket): string
    {
        $url = url("/tickets/{$ticket->id}");
        $body = strip_tags((string) ($ticket->description ?: 'No description provided.'));

        return Str::limit($body, 3000)."\n\nhelpefi ticket: {$url}";
    }
}
