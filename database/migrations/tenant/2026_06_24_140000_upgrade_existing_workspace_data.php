<?php

use App\Domains\Knowledge\Support\PlatformKnowledge;
use App\Domains\Tickets\Services\TicketNumberGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizePlatformHandbookMetadata();
        app(TicketNumberGenerator::class)->syncFromExistingTickets();
    }

    public function down(): void
    {
    }

    private function normalizePlatformHandbookMetadata(): void
    {
        if (! Schema::hasTable('knowledge_collections') || ! Schema::hasTable('knowledge_articles')) {
            return;
        }

        DB::table('knowledge_collections')
            ->where('slug', PlatformKnowledge::HANDBOOK_COLLECTION_SLUG)
            ->update([
                'is_system' => true,
                'is_public' => false,
            ]);

        DB::table('knowledge_articles')
            ->whereIn('slug', PlatformKnowledge::HANDBOOK_ARTICLE_SLUGS)
            ->update(['is_system' => true]);

        if (Schema::hasColumn('knowledge_articles', 'is_public')) {
            DB::table('knowledge_articles')
                ->where('is_system', true)
                ->whereNull('is_public')
                ->update(['is_public' => false]);
        }
    }
};
