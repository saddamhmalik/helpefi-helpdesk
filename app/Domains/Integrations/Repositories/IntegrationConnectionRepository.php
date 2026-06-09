<?php

namespace App\Domains\Integrations\Repositories;

use App\Domains\Integrations\Models\IntegrationConnection;
use Illuminate\Database\Eloquent\Collection;

class IntegrationConnectionRepository
{
    public function all(): Collection
    {
        return IntegrationConnection::query()->orderBy('provider')->get();
    }

    public function findByProvider(string $provider): ?IntegrationConnection
    {
        return IntegrationConnection::query()->where('provider', $provider)->first();
    }

    public function activeForProvider(string $provider): ?IntegrationConnection
    {
        return IntegrationConnection::query()
            ->where('provider', $provider)
            ->where('is_active', true)
            ->first();
    }

    public function upsert(string $provider, array $data): IntegrationConnection
    {
        $connection = IntegrationConnection::query()->firstOrNew(['provider' => $provider]);
        $connection->fill($data);
        $connection->save();

        return $connection->fresh();
    }

    public function recordDelivery(IntegrationConnection $connection, bool $successful, ?string $error = null): void
    {
        $connection->update([
            'last_delivered_at' => now(),
            'last_error' => $successful ? null : $error,
        ]);
    }
}
