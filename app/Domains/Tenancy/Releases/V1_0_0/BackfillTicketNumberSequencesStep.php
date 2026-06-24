<?php

namespace App\Domains\Tenancy\Releases\V1_0_0;

use App\Domains\Tenancy\Releases\AbstractTenantReleaseStep;
use App\Domains\Tenancy\Services\TenantWorkspaceUpgradeService;

class BackfillTicketNumberSequencesStep extends AbstractTenantReleaseStep
{
    public function key(): string
    {
        return 'backfill_ticket_number_sequences';
    }

    public function description(): string
    {
        return 'Backfill ticket number sequences from existing tickets.';
    }

    public function run(): void
    {
        app(TenantWorkspaceUpgradeService::class)->syncTicketNumberSequences();
    }
}
