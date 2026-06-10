<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Billing\Services\BillingService;
use App\Domains\Integrations\Models\Webhook;
use App\Domains\Integrations\Repositories\WebhookRepository;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class WebhookService
{
    public function __construct(
        private WebhookRepository $webhooks,
        private BillingService $billing,
        private AuditRecorder $audit,
    ) {
    }

    public function all(): Collection
    {
        return $this->webhooks->all();
    }

    public function create(array $data): Webhook
    {
        $this->billing->assertFeature('integrations');
        $this->assertValidWebhook($data);

        $data['secret'] = $data['secret'] ?? Str::random(32);

        $webhook = $this->webhooks->create($data);

        $this->audit->record('webhook.created', $webhook, [
            'name' => $webhook->name,
            'url' => $webhook->url,
        ]);

        return $webhook;
    }

    public function update(int $id, array $data): Webhook
    {
        $this->assertValidWebhook($data);

        $webhook = $this->webhooks->find($id);

        if (empty($data['secret'])) {
            unset($data['secret']);
        }

        $webhook = $this->webhooks->update($webhook, $data);

        $this->audit->record('webhook.updated', $webhook, [
            'name' => $webhook->name,
            'url' => $webhook->url,
        ]);

        return $webhook;
    }

    public function delete(int $id): void
    {
        $webhook = $this->webhooks->find($id);
        $this->webhooks->delete($webhook);

        $this->audit->record('webhook.deleted', $webhook, [
            'name' => $webhook->name,
        ]);
    }

    public function regenerateSecret(int $id): Webhook
    {
        $webhook = $this->webhooks->update($this->webhooks->find($id), [
            'secret' => Str::random(32),
        ]);

        $this->audit->record('webhook.updated', $webhook, [
            'name' => $webhook->name,
            'secret_regenerated' => true,
        ]);

        return $webhook;
    }

    public function dispatchForTicket(Ticket $ticket, string $event, array $context = []): void
    {
        $payload = $this->ticketPayload($ticket, $context);

        foreach ($this->webhooks->activeForEvent($event) as $webhook) {
            $this->deliver($webhook, $event, $payload);
        }
    }

    public function deliverAutomation(int $webhookId, Ticket $ticket, array $context = []): bool
    {
        $webhook = $this->webhooks->find($webhookId);

        if (! $webhook->is_active) {
            return false;
        }

        return $this->deliver($webhook, Webhook::EVENT_AUTOMATION, $this->ticketPayload($ticket, array_merge($context, [
            'source' => 'automation',
        ])));
    }

    public function sendTest(int $id): bool
    {
        $webhook = $this->webhooks->find($id);

        $result = $this->deliver($webhook, Webhook::EVENT_TEST, [
            'message' => 'helpefi webhook test delivery',
        ]);

        $this->audit->record('webhook.tested', $webhook, [
            'name' => $webhook->name,
            'success' => $result,
        ]);

        return $result;
    }

    public function meta(): array
    {
        return [
            'events' => [
                ['value' => Webhook::EVENT_TICKET_CREATED, 'label' => 'Ticket created'],
                ['value' => Webhook::EVENT_TICKET_UPDATED, 'label' => 'Ticket updated'],
                ['value' => Webhook::EVENT_CUSTOMER_MESSAGE, 'label' => 'Customer message received'],
            ],
        ];
    }

    private function deliver(Webhook $webhook, string $event, array $payload): bool
    {
        $body = [
            'event' => $event,
            'payload' => $payload,
            'timestamp' => now()->toIso8601String(),
        ];

        $encoded = json_encode($body, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $encoded, $webhook->secret);

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'X-Helpdesk-Event' => $event,
                    'X-Helpdesk-Signature' => $signature,
                    'Content-Type' => 'application/json',
                ])
                ->withBody($encoded, 'application/json')
                ->post($webhook->url);

            $successful = $response->successful();

            $this->webhooks->recordDelivery(
                $webhook,
                $event,
                $successful,
                $response->status(),
                $successful ? null : Str::limit($response->body(), 500),
            );

            return $successful;
        } catch (Throwable $exception) {
            $this->webhooks->recordDelivery(
                $webhook,
                $event,
                false,
                null,
                Str::limit($exception->getMessage(), 500),
            );

            return false;
        }
    }

    private function ticketPayload(Ticket $ticket, array $context): array
    {
        $ticket->loadMissing(['status:id,name,slug', 'priority:id,name,slug', 'channel:id,name,slug,type', 'contact:id,name,email']);

        return [
            'id' => $ticket->id,
            'number' => $ticket->number,
            'subject' => $ticket->subject,
            'status' => $ticket->status?->slug,
            'priority' => $ticket->priority?->slug,
            'channel' => $ticket->channel?->slug,
            'contact' => $ticket->contact ? [
                'id' => $ticket->contact->id,
                'name' => $ticket->contact->name,
                'email' => $ticket->contact->email,
            ] : null,
            'assigned_to' => $ticket->assigned_to,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
            'context' => $context,
        ];
    }

    private function assertValidWebhook(array $data): void
    {
        $validEvents = [
            Webhook::EVENT_TICKET_CREATED,
            Webhook::EVENT_TICKET_UPDATED,
            Webhook::EVENT_CUSTOMER_MESSAGE,
        ];

        if (empty($data['events']) || ! is_array($data['events'])) {
            throw new InvalidArgumentException('Webhook requires at least one event.');
        }

        foreach ($data['events'] as $event) {
            if (! in_array($event, $validEvents, true)) {
                throw new InvalidArgumentException('Invalid webhook event.');
            }
        }
    }
}
