<?php

namespace App\Domains\Integrations\Repositories;

use App\Domains\Integrations\Models\ContactCrmProfile;

class CrmProfileRepository
{
    public function findForContact(int $contactId): ?ContactCrmProfile
    {
        return ContactCrmProfile::query()
            ->where('contact_id', $contactId)
            ->first();
    }

    public function upsert(int $contactId, string $provider, string $externalId, array $profile): ContactCrmProfile
    {
        return ContactCrmProfile::query()->updateOrCreate(
            ['contact_id' => $contactId],
            [
                'provider' => $provider,
                'external_id' => $externalId,
                'profile' => $profile,
                'synced_at' => now(),
            ],
        );
    }
}
