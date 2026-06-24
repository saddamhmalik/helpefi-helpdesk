<?php

namespace App\Domains\Tickets\Observers;

use App\Support\ReferenceCacheInvalidator;
use Illuminate\Database\Eloquent\Model;

class TicketFormReferenceCacheObserver
{
    public function __construct(private ReferenceCacheInvalidator $referenceCache)
    {
    }

    public function saved(Model $model): void
    {
        $this->referenceCache->forgetTicketFormReference();
    }

    public function deleted(Model $model): void
    {
        $this->referenceCache->forgetTicketFormReference();
    }
}
