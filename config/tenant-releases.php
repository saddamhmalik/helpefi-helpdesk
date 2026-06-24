<?php

use App\Domains\Tenancy\Releases\V1_0_0\BackfillTicketNumberSequencesStep;
use App\Domains\Tenancy\Releases\V1_0_0\ClearWorkspaceCachesStep;
use App\Domains\Tenancy\Releases\V1_0_0\NormalizeHandbookMetadataStep;
use App\Domains\Tenancy\Releases\V1_0_0\SyncAgentPermissionsStep;
use App\Domains\Tenancy\Releases\V1_0_0\SyncPlatformHandbookStep;

return [

    'releases' => [
        '0.0.1' => [
            'description' => 'Pre-release-tracking baseline for workspaces provisioned before tenant release upgrades.',
            'steps' => [],
        ],
        '1.0.0' => [
            'description' => 'Production readiness data upgrades: ticket numbering, handbook metadata, platform handbook, permissions, caches.',
            'steps' => [
                BackfillTicketNumberSequencesStep::class,
                NormalizeHandbookMetadataStep::class,
                SyncPlatformHandbookStep::class,
                SyncAgentPermissionsStep::class,
                ClearWorkspaceCachesStep::class,
            ],
        ],
    ],

];
