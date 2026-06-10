<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Models\IntegrationConnection;
use App\Domains\Integrations\Repositories\IntegrationConnectionRepository;
use HubSpot\Factory as HubSpotFactory;

class HubspotIntegrationService
{
    public function __construct(private IntegrationConnectionRepository $connections)
    {
    }

    public function lookupContactByEmail(string $email): ?array
    {
        $connection = $this->connections->findByProvider(IntegrationConnection::PROVIDER_HUBSPOT);

        if (! $connection?->is_active || empty($connection->config['access_token'])) {
            return null;
        }

        $client = HubSpotFactory::createWithAccessToken($connection->config['access_token']);
        $response = $client->crm()->contacts()->searchApi()->doSearch([
            'filterGroups' => [[
                'filters' => [[
                    'propertyName' => 'email',
                    'operator' => 'EQ',
                    'value' => $email,
                ]],
            ]],
            'properties' => [
                'firstname',
                'lastname',
                'email',
                'company',
                'phone',
                'lifecyclestage',
                'hubspot_owner_id',
                'recent_deal_amount',
            ],
            'limit' => 1,
        ]);

        $result = $response->getResults()[0] ?? null;

        if (! $result) {
            return null;
        }

        $properties = $result->getProperties();
        $ownerId = $properties['hubspot_owner_id'] ?? null;
        $ownerName = $ownerId ? $this->resolveOwnerName($client, (string) $ownerId) : null;
        $dealValue = isset($properties['recent_deal_amount']) && $properties['recent_deal_amount'] !== ''
            ? (float) $properties['recent_deal_amount']
            : null;

        return [
            'provider' => IntegrationConnection::PROVIDER_HUBSPOT,
            'id' => $result->getId(),
            'profile' => [
                'name' => trim(($properties['firstname'] ?? '').' '.($properties['lastname'] ?? '')),
                'email' => $properties['email'] ?? $email,
                'company' => $properties['company'] ?? null,
                'phone' => $properties['phone'] ?? null,
                'lifecycle_stage' => $properties['lifecyclestage'] ?? null,
                'deal_value' => $dealValue,
                'owner' => $ownerName,
                'url' => 'https://app.hubspot.com/contacts/'.$result->getId(),
            ],
        ];
    }

    public function testConnection(): bool
    {
        $connection = $this->connections->findByProvider(IntegrationConnection::PROVIDER_HUBSPOT);

        if (! $connection?->is_active || empty($connection->config['access_token'])) {
            return false;
        }

        $client = HubSpotFactory::createWithAccessToken($connection->config['access_token']);
        $client->crm()->owners()->ownersApi()->getPage(null, null, 1);

        return true;
    }

    private function resolveOwnerName($client, string $ownerId): ?string
    {
        try {
            $owner = $client->crm()->owners()->ownersApi()->getById((int) $ownerId);

            return trim(($owner->getFirstName() ?? '').' '.($owner->getLastName() ?? '')) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
