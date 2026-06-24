<?php

namespace App\Domains\Tickets\Observers;

use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Tickets\Services\TicketStatusLookup;
use App\Support\ReferenceCacheInvalidator;

class TicketStatusCacheObserver
{
    public function __construct(private ReferenceCacheInvalidator $referenceCache)
    {
    }

    public function saved(TicketStatus $status): void
    {
        TicketStatusLookup::forget();
        $this->referenceCache->forgetTicketFormReference();
    }

    public function deleted(TicketStatus $status): void
    {
        TicketStatusLookup::forget();
        $this->referenceCache->forgetTicketFormReference();
    }
}
