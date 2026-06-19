<?php

namespace App\Domains\Tenancy\Jobs;

use App\Domains\Tenancy\Services\TenantInfrastructureService;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stancl\Tenancy\Events\DatabaseDeleted;
use Stancl\Tenancy\Events\DeletingDatabase;

class DeleteTenantDatabaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Tenant $tenant)
    {
    }

    public function handle(TenantInfrastructureService $infrastructure): void
    {
        if ($infrastructure->usesExternalDatabase($this->tenant)) {
            return;
        }

        if ($this->tenant->getInternal('create_database') === false) {
            return;
        }

        event(new DeletingDatabase($this->tenant));

        $this->tenant->database()->manager()->deleteDatabase($this->tenant);

        event(new DatabaseDeleted($this->tenant));
    }
}
