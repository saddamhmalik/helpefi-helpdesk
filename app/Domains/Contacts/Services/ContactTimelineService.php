<?php

namespace App\Domains\Contacts\Services;

use App\Domains\Contacts\Repositories\ContactTimelineRepository;

class ContactTimelineService
{
    public function __construct(private ContactTimelineRepository $timeline)
    {
    }

    public function forContact(int $contactId, int $limit = 60): array
    {
        return $this->timeline->collect($contactId, $limit);
    }
}
