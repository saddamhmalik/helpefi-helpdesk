<?php

namespace App\Support;

use App\Domains\Contacts\Support\ContactFormReferenceCache;
use App\Domains\Tickets\Support\AssignableAgentCache;
use App\Domains\Tickets\Support\TicketFormReferenceCache;

class ReferenceCacheInvalidator
{
    public function forgetTicketFormReference(): void
    {
        TicketFormReferenceCache::forget();
        AssignableAgentCache::forget();
    }

    public function forgetContactFormReference(): void
    {
        ContactFormReferenceCache::forget();
    }

    public function forgetAll(): void
    {
        $this->forgetTicketFormReference();
        $this->forgetContactFormReference();
    }
}
