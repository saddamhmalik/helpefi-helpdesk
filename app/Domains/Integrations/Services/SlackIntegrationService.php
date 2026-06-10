<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Models\IntegrationConnection;
use App\Domains\Integrations\Models\Webhook;
use App\Domains\Integrations\Repositories\IntegrationConnectionRepository;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class SlackIntegrationService
{
    public function __construct(private IntegrationConnectionRepository $connections)
    {
    }

    public function notify(Ticket $ticket, string $event, array $context = []): bool
    {
        $connection = $this->connections->activeForProvider(IntegrationConnection::PROVIDER_SLACK);

        if (! $connection || ! $this->subscribed($connection, $event)) {
            return false;
        }

        $config = $connection->config ?? [];
        $webhookUrl = $config['webhook_url'] ?? null;

        if (! $webhookUrl) {
            return false;
        }

        $ticket->loadMissing(['status:id,name,slug', 'priority:id,name,slug', 'contact:id,name,email']);

        $payload = [
            'text' => $this->messageText($ticket, $event, $context),
        ];

        if (! empty($config['channel'])) {
            $payload['channel'] = $config['channel'];
        }

        try {
            $response = Http::timeout(5)->post($webhookUrl, $payload);
            $successful = $response->successful();

            $this->connections->recordDelivery(
                $connection,
                $successful,
                $successful ? null : Str::limit($response->body(), 500),
            );

            return $successful;
        } catch (Throwable $exception) {
            $this->connections->recordDelivery($connection, false, Str::limit($exception->getMessage(), 500));

            return false;
        }
    }

    public function sendTest(): bool
    {
        $connection = $this->connections->findByProvider(IntegrationConnection::PROVIDER_SLACK);

        if (! $connection) {
            return false;
        }

        $webhookUrl = $connection->config['webhook_url'] ?? null;

        if (! $webhookUrl) {
            return false;
        }

        try {
            $response = Http::timeout(5)->post($webhookUrl, [
                'text' => 'helpefi Slack integration test — notifications are working.',
            ]);

            $successful = $response->successful();
            $this->connections->recordDelivery(
                $connection,
                $successful,
                $successful ? null : Str::limit($response->body(), 500),
            );

            return $successful;
        } catch (Throwable $exception) {
            $this->connections->recordDelivery($connection, false, Str::limit($exception->getMessage(), 500));

            return false;
        }
    }

    private function subscribed(IntegrationConnection $connection, string $event): bool
    {
        $events = $connection->events ?? [
            Webhook::EVENT_TICKET_CREATED,
            Webhook::EVENT_TICKET_UPDATED,
            Webhook::EVENT_CUSTOMER_MESSAGE,
        ];

        return in_array($event, $events, true);
    }

    private function messageText(Ticket $ticket, string $event, array $context): string
    {
        $url = url("/tickets/{$ticket->id}");
        $label = match ($event) {
            Webhook::EVENT_TICKET_CREATED => 'created',
            Webhook::EVENT_TICKET_UPDATED => 'updated',
            Webhook::EVENT_CUSTOMER_MESSAGE => 'received a customer message on',
            default => 'event on',
        };

        $lines = [
            "*{$ticket->number}* {$label}",
            "<{$url}|Open ticket>",
            "Subject: {$ticket->subject}",
        ];

        if ($ticket->status?->name) {
            $lines[] = "Status: {$ticket->status->name}";
        }

        if ($ticket->contact?->name) {
            $lines[] = "Contact: {$ticket->contact->name}";
        }

        if ($event === Webhook::EVENT_CUSTOMER_MESSAGE && ! empty($context['message_body'])) {
            $lines[] = 'Message: '.Str::limit(strip_tags((string) $context['message_body']), 200);
        }

        return implode("\n", $lines);
    }
}
