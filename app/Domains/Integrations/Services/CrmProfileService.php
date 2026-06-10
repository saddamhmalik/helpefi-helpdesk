<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Repositories\ContactRepository;
use App\Domains\Integrations\Models\IntegrationConnection;
use App\Domains\Integrations\Repositories\CrmProfileRepository;
use App\Domains\Integrations\Repositories\IntegrationConnectionRepository;

class CrmProfileService
{
    public function __construct(
        private IntegrationConnectionRepository $connections,
        private CrmProfileRepository $profiles,
        private HubspotIntegrationService $hubspot,
        private SalesforceIntegrationService $salesforce,
        private ContactRepository $contacts,
    ) {
    }

    public function shouldSync(): bool
    {
        return $this->connections->findByProvider(IntegrationConnection::PROVIDER_HUBSPOT)?->is_active
            || $this->connections->findByProvider(IntegrationConnection::PROVIDER_SALESFORCE)?->is_active;
    }

    public function syncForContact(Contact $contact): ?array
    {
        $email = $contact->email;

        if (! $email || ! $this->shouldSync()) {
            return null;
        }

        $lookup = $this->hubspot->lookupContactByEmail($email)
            ?? $this->salesforce->lookupContactByEmail($email);

        if (! $lookup) {
            return null;
        }

        $provider = $lookup['provider'];
        $profile = $lookup['profile'];
        $externalId = (string) $lookup['id'];

        $this->profiles->upsert($contact->id, $provider, $externalId, $profile);
        $this->applyContactSync($contact, $profile);

        return $this->formatSnapshot($provider, $externalId, $profile, now());
    }

    public function snapshotForContact(Contact $contact, bool $refresh = false): ?array
    {
        $cached = $this->profiles->findForContact($contact->id);

        if ($refresh || (! $cached && $this->shouldSync() && $contact->email)) {
            $fresh = $this->syncForContact($contact);

            if ($fresh) {
                return $fresh;
            }
        }

        if (! $cached) {
            return null;
        }

        return $this->formatSnapshot(
            $cached->provider,
            $cached->external_id,
            $cached->profile ?? [],
            $cached->synced_at,
        );
    }

    private function applyContactSync(Contact $contact, array $profile): void
    {
        $updates = [];

        if (! $contact->name && ! empty($profile['name'])) {
            $updates['name'] = $profile['name'];
        }

        if (! $contact->phone && ! empty($profile['phone'])) {
            $updates['phone'] = $profile['phone'];
        }

        if (! $contact->company && ! empty($profile['company'])) {
            $updates['company'] = $profile['company'];
        }

        if ($updates !== []) {
            $this->contacts->update($contact, $updates);
        }
    }

    private function formatSnapshot(string $provider, string $externalId, array $profile, $syncedAt): array
    {
        return [
            'provider' => $provider,
            'provider_label' => $provider === IntegrationConnection::PROVIDER_HUBSPOT ? 'HubSpot' : 'Salesforce',
            'external_id' => $externalId,
            'name' => $profile['name'] ?? null,
            'company' => $profile['company'] ?? null,
            'phone' => $profile['phone'] ?? null,
            'lifecycle_stage' => $profile['lifecycle_stage'] ?? null,
            'deal_value' => $profile['deal_value'] ?? null,
            'owner' => $profile['owner'] ?? null,
            'url' => $profile['url'] ?? null,
            'synced_at' => $syncedAt?->toIso8601String(),
        ];
    }
}
