<?php

namespace App\Domains\Contacts\Repositories;

use App\Domains\Contacts\Models\ContactActivity;
use App\Domains\Contacts\Models\ContactNote;
use App\Domains\Contacts\Models\OrganizationDomain;

class ContactActivityRepository
{
    public function log(int $contactId, ?int $userId, string $type, string $description, ?array $metadata = null): ContactActivity
    {
        return ContactActivity::query()->create([
            'contact_id' => $contactId,
            'user_id' => $userId,
            'type' => $type,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    public function findOrganizationByEmailDomain(string $email): ?int
    {
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));

        if ($domain === '') {
            return null;
        }

        return OrganizationDomain::query()
            ->where('domain', $domain)
            ->value('organization_id');
    }

    public function addNote(int $contactId, int $userId, string $body): ContactNote
    {
        return ContactNote::query()->create([
            'contact_id' => $contactId,
            'user_id' => $userId,
            'body' => $body,
        ]);
    }
}
