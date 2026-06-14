<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tickets')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->index(['merged_into_ticket_id', 'updated_at'], 'tickets_queue_idx');
            });
        }

        if (Schema::hasTable('ticket_messages')) {
            Schema::table('ticket_messages', function (Blueprint $table) {
                $table->index(['ticket_id', 'contact_id', 'is_internal'], 'ticket_messages_unread_idx');
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_unread_idx');
            });
        }

        if (Schema::hasTable('ticket_sla_timers')) {
            Schema::table('ticket_sla_timers', function (Blueprint $table) {
                $table->index(['first_response_breached', 'first_response_due_at'], 'sla_first_response_breach_idx');
                $table->index(['resolution_breached', 'resolution_due_at'], 'sla_resolution_breach_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tickets')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropIndex('tickets_queue_idx');
            });
        }

        if (Schema::hasTable('ticket_messages')) {
            Schema::table('ticket_messages', function (Blueprint $table) {
                $table->dropIndex('ticket_messages_unread_idx');
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndex('notifications_unread_idx');
            });
        }

        if (Schema::hasTable('ticket_sla_timers')) {
            Schema::table('ticket_sla_timers', function (Blueprint $table) {
                $table->dropIndex('sla_first_response_breach_idx');
                $table->dropIndex('sla_resolution_breach_idx');
            });
        }
    }
};
