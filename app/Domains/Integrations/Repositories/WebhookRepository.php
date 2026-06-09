<?php

namespace App\Domains\Integrations\Repositories;

use App\Domains\Integrations\Models\Webhook;
use App\Domains\Integrations\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Collection;

class WebhookRepository
{
    public function all(): Collection
    {
        return Webhook::query()
            ->with(['deliveries' => fn ($q) => $q->limit(5)])
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): Webhook
    {
        return Webhook::query()
            ->with(['deliveries' => fn ($q) => $q->limit(10)])
            ->findOrFail($id);
    }

    public function activeForEvent(string $event): Collection
    {
        return Webhook::query()
            ->where('is_active', true)
            ->whereJsonContains('events', $event)
            ->get();
    }

    public function create(array $data): Webhook
    {
        return Webhook::query()->create($data);
    }

    public function update(Webhook $webhook, array $data): Webhook
    {
        $webhook->update($data);

        return $webhook->fresh(['deliveries' => fn ($q) => $q->limit(10)]);
    }

    public function delete(Webhook $webhook): void
    {
        $webhook->delete();
    }

    public function recordDelivery(
        Webhook $webhook,
        string $event,
        bool $successful,
        ?int $statusCode,
        ?string $errorMessage = null,
    ): WebhookDelivery {
        $webhook->update([
            'last_delivered_at' => now(),
            'last_status_code' => $statusCode,
        ]);

        $delivery = $webhook->deliveries()->create([
            'event' => $event,
            'successful' => $successful,
            'status_code' => $statusCode,
            'error_message' => $errorMessage,
        ]);

        $keepIds = WebhookDelivery::query()
            ->where('webhook_id', $webhook->id)
            ->orderByDesc('id')
            ->limit(50)
            ->pluck('id');

        if ($keepIds->isNotEmpty()) {
            WebhookDelivery::query()
                ->where('webhook_id', $webhook->id)
                ->whereNotIn('id', $keepIds)
                ->delete();
        }

        return $delivery;
    }
}
