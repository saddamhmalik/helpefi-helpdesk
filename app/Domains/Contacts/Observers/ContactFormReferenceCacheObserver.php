<?php

namespace App\Domains\Contacts\Observers;

use App\Support\ReferenceCacheInvalidator;
use Illuminate\Database\Eloquent\Model;

class ContactFormReferenceCacheObserver
{
    public function __construct(private ReferenceCacheInvalidator $referenceCache)
    {
    }

    public function saved(Model $model): void
    {
        $this->referenceCache->forgetContactFormReference();
    }

    public function deleted(Model $model): void
    {
        $this->referenceCache->forgetContactFormReference();
    }
}
