<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket_agent_reads') || Schema::hasColumn('ticket_agent_reads', 'unread_count')) {
            return;
        }

        Schema::table('ticket_agent_reads', function (Blueprint $table) {
            $table->unsignedInteger('unread_count')->default(0)->after('last_read_message_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ticket_agent_reads') || ! Schema::hasColumn('ticket_agent_reads', 'unread_count')) {
            return;
        }

        Schema::table('ticket_agent_reads', function (Blueprint $table) {
            $table->dropColumn('unread_count');
        });
    }
};
