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
                'teamId' => $this->teamId($config),
                'title' => "[{$ticket->number}] {$ticket->subject}",
                'description' => $this->issueDescription($ticket),
            ],
        ]);

        $result = $response['data']['issueCreate'] ?? null;
        $issue = $result['issue'] ?? null;

        if (! ($result['success'] ?? false) || ! $issue) {
            throw new InvalidArgumentException('Linear could not create the issue. Verify the team ID and API key permissions.');
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
                'teamId' => $this->teamId($config),
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
        ', ['teamId' => $this->teamId($config)]);

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

    public function isConfigured(): bool
    {
        try {
            $this->config();

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public static function normalizeTeamReference(string $teamId): string
    {
        $teamId = trim($teamId);

        if (preg_match('~linear\.app/(?:[^/]+/)?(?:settings/teams|team)/([^/?#]+)~i', $teamId, $matches)) {
            return $matches[1];
        }

        if (preg_match('~/teams/([^/?#]+)/?$~i', $teamId, $matches)) {
            return $matches[1];
        }

        return $teamId;
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
        $payload = ['query' => $query];

        if ($variables !== []) {
            $payload['variables'] = $variables;
        }

        $body = Http::withHeaders([
                'Authorization' => $this->normalizeApiKey($config['api_key']),
            ])
            ->acceptJson()
            ->post('https://api.linear.app/graphql', $payload)
            ->throw()
            ->json();

        if (! empty($body['errors'])) {
            throw new InvalidArgumentException($this->formatGraphqlErrors($body['errors']));
        }

        return $body;
    }

    private function teamId(array $config): string
    {
        $reference = self::normalizeTeamReference((string) $config['team_id']);

        if ($this->looksLikeUuid($reference)) {
            return $reference;
        }

        if ($teamId = $this->findTeamById($config, $reference)) {
            return $teamId;
        }

        return $this->findTeamByReference($config, $reference);
    }

    private function findTeamById(array $config, string $reference): ?string
    {
        $response = $this->graphql($config, '
            query Team($id: String!) {
                team(id: $id) {
                    id
                }
            }
        ', ['id' => $reference]);

        $id = $response['data']['team']['id'] ?? null;

        return $id ? (string) $id : null;
    }

    private function findTeamByReference(array $config, string $reference): string
    {
        $response = $this->graphql($config, '
            query Teams {
                teams {
                    nodes {
                        id
                        key
                        name
                    }
                }
            }
        ');

        $needle = strtolower($reference);

        $team = collect($response['data']['teams']['nodes'] ?? [])
            ->first(function (array $team) use ($needle) {
                return in_array($needle, [
                    strtolower((string) ($team['key'] ?? '')),
                    strtolower((string) ($team['name'] ?? '')),
                ], true);
            });

        if (! $team) {
            $available = collect($response['data']['teams']['nodes'] ?? [])
                ->map(fn (array $team) => (string) ($team['key'] ?? ''))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->implode(', ');

            $suffix = $available !== ''
                ? " Available teams: {$available}."
                : ' Check that your API key can access the workspace teams.';

            throw new InvalidArgumentException(
                "Linear team \"{$reference}\" was not found. Paste the team URL, team key, or UUID from Linear settings.{$suffix}"
            );
        }

        return (string) $team['id'];
    }

    private function looksLikeUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    private function formatGraphqlErrors(array $errors): string
    {
        $messages = collect($errors)
            ->map(fn (array $error) => trim((string) ($error['message'] ?? '')))
            ->filter()
            ->unique()
            ->values();

        if ($messages->isEmpty()) {
            return 'Linear API request failed.';
        }

        return $messages->implode(' ');
    }

    private function normalizeApiKey(string $apiKey): string
    {
        return preg_replace('/^Bearer\s+/i', '', trim($apiKey));
    }

    private function issueDescription(Ticket $ticket): string
    {
        $url = url("/tickets/{$ticket->id}");
        $body = strip_tags((string) ($ticket->description ?: 'No description provided.'));

        return Str::limit($body, 3000)."\n\nhelpefi ticket: {$url}";
    }
}
