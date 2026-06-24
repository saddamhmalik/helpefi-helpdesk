<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_messages')) {
            Schema::table('ticket_messages', function (Blueprint $table) {
                $table->index(['ticket_id', 'created_at', 'id'], 'ticket_messages_timeline_idx');
            });
        }

        if (Schema::hasTable('ticket_attachments')) {
            Schema::table('ticket_attachments', function (Blueprint $table) {
                $table->index(['ticket_id', 'filename', 'size'], 'ticket_attachments_dedup_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ticket_messages')) {
            Schema::table('ticket_messages', function (Blueprint $table) {
                $table->dropIndex('ticket_messages_timeline_idx');
            });
        }

        if (Schema::hasTable('ticket_attachments')) {
            Schema::table('ticket_attachments', function (Blueprint $table) {
                $table->dropIndex('ticket_attachments_dedup_idx');
            });
        }
    }
};
