<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tickets') && $this->isMysqlFamily() && ! $this->indexExists('tickets', 'tickets_search_fulltext')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->fullText(['subject', 'number'], 'tickets_search_fulltext');
            });
        }

        if (Schema::hasTable('contacts') && ! $this->indexExists('contacts', 'contacts_name_idx')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->index('name', 'contacts_name_idx');
            });
        }

        if (Schema::hasTable('ticket_agent_reads') && ! Schema::hasColumn('ticket_agent_reads', 'unread_count')) {
            Schema::table('ticket_agent_reads', function (Blueprint $table) {
                $table->unsignedInteger('unread_count')->default(0)->after('last_read_message_id');
            });
        }

        if (! Schema::hasTable('ticket_number_sequences')) {
            Schema::create('ticket_number_sequences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedBigInteger('last_value')->default(0);
                $table->timestamps();
                $table->unique('brand_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tickets') && $this->isMysqlFamily() && $this->indexExists('tickets', 'tickets_search_fulltext')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropFullText('tickets_search_fulltext');
            });
        }

        if (Schema::hasTable('contacts') && $this->indexExists('contacts', 'contacts_name_idx')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->dropIndex('contacts_name_idx');
            });
        }

        if (Schema::hasTable('ticket_agent_reads') && Schema::hasColumn('ticket_agent_reads', 'unread_count')) {
            Schema::table('ticket_agent_reads', function (Blueprint $table) {
                $table->dropColumn('unread_count');
            });
        }

        Schema::dropIfExists('ticket_number_sequences');
    }

    private function isMysqlFamily(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function indexExists(string $table, string $index): bool
    {
        if (! $this->isMysqlFamily()) {
            return false;
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', Schema::getConnection()->getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
