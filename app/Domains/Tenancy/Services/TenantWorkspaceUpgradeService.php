<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Knowledge\Support\HelpCenterPublicCache;
use App\Domains\Knowledge\Support\PlatformKnowledge;
use App\Domains\Tickets\Services\TicketNumberGenerator;
use App\Support\ReferenceCacheInvalidator;
use Database\Seeders\PlatformHandbookSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantWorkspaceUpgradeService
{
    public function __construct(
        private TicketNumberGenerator $ticketNumbers,
        private ReferenceCacheInvalidator $referenceCache,
    ) {
    }

    public function upgrade(bool $seedHandbook = true, bool $clearCaches = true): array
    {
        return [
            'ticket_sequences' => $this->syncTicketNumberSequences(),
            'handbook_metadata' => $this->normalizePlatformHandbookMetadata(),
            'handbook_seeded' => $seedHandbook ? $this->ensurePlatformHandbook() : false,
            'caches_cleared' => $clearCaches ? $this->clearWorkspaceCaches() : false,
        ];
    }

    public function syncTicketNumberSequences(): int
    {
        return $this->ticketNumbers->syncFromExistingTickets();
    }

    public function normalizePlatformHandbookMetadata(): int
    {
        if (! Schema::hasTable('knowledge_collections') || ! Schema::hasTable('knowledge_articles')) {
            return 0;
        }

        $updated = 0;

        $updated += DB::table('knowledge_collections')
            ->where('slug', PlatformKnowledge::HANDBOOK_COLLECTION_SLUG)
            ->update([
                'is_system' => true,
                'is_public' => false,
            ]);

        $updated += DB::table('knowledge_articles')
            ->whereIn('slug', PlatformKnowledge::HANDBOOK_ARTICLE_SLUGS)
            ->update(['is_system' => true]);

        $updated += DB::table('knowledge_articles')
            ->where('is_system', true)
            ->whereNull('is_public')
            ->update(['is_public' => false]);

        return $updated;
    }

    public function ensurePlatformHandbook(): bool
    {
        if (! Schema::hasTable('knowledge_collections')) {
            return false;
        }

        Artisan::call('db:seed', [
            '--class' => PlatformHandbookSeeder::class,
            '--force' => true,
        ]);

        return true;
    }

    public function clearWorkspaceCaches(): bool
    {
        HelpCenterPublicCache::forgetAll();
        $this->referenceCache->forgetAll();

        return true;
    }
}
