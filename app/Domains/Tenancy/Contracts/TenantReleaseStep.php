<?php

namespace App\Domains\Tenancy\Contracts;

interface TenantReleaseStep
{
    public function release(): string;

    public function key(): string;

    public function description(): string;

    public function run(): void;
}
