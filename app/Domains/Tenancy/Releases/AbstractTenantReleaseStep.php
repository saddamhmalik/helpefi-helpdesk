<?php

namespace App\Domains\Tenancy\Releases;

use App\Domains\Tenancy\Contracts\TenantReleaseStep;

abstract class AbstractTenantReleaseStep implements TenantReleaseStep
{
    public function __construct(private string $release)
    {
    }

    public function release(): string
    {
        return $this->release;
    }

    public function identifier(): string
    {
        return $this->release.':'.$this->key();
    }
}
