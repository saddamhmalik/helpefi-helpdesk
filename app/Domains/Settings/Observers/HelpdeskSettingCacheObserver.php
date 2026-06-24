<?php

namespace App\Domains\Settings\Observers;

use App\Support\ReferenceCacheInvalidator;
use Illuminate\Database\Eloquent\Model;

class HelpdeskSettingCacheObserver
{
    public function __construct(private ReferenceCacheInvalidator $referenceCache)
    {
    }

    public function saved(Model $model): void
    {
        $this->referenceCache->forgetAll();
    }

    public function deleted(Model $model): void
    {
        $this->referenceCache->forgetAll();
    }
}
