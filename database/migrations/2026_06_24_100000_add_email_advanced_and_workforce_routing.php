<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_settings', function (Blueprint $table) {
            $table->boolean('email_allow_agent_initiated')->default(false)->after('email_blocklist');
            $table->boolean('email_use_agent_name_in_from')->default(false)->after('email_allow_agent_initiated');
            $table->string('email_automatic_bcc')->nullable()->after('email_use_agent_name_in_from');
            $table->string('email_reply_to_address')->nullable()->after('email_automatic_bcc');
            $table->boolean('email_use_reply_to_as_requester')->default(false)->after('email_reply_to_address');
            $table->boolean('email_use_original_sender_for_forwarded')->default(true)->after('email_use_reply_to_as_requester');
            $table->boolean('email_flexible_recipients')->default(true)->after('email_use_original_sender_for_forwarded');
            $table->boolean('email_ignore_ticket_id_threading')->default(false)->after('email_flexible_recipients');
            $table->boolean('email_create_ticket_on_subject_change')->default(false)->after('email_ignore_ticket_id_threading');
            $table->boolean('email_detect_auto_replies')->default(true)->after('email_create_ticket_on_subject_change');
        });

        Schema::table('email_inboxes', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('brand_id')->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
            $table->json('aliases')->nullable()->after('address');
        });

        Schema::table('mail_settings', function (Blueprint $table) {
            $table->string('reply_to_address')->nullable()->after('from_name');
            $table->string('automatic_bcc')->nullable()->after('reply_to_address');
            $table->boolean('use_agent_name_in_from')->default(false)->after('automatic_bcc');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::table('mail_settings', function (Blueprint $table) {
            $table->dropColumn(['reply_to_address', 'automatic_bcc', 'use_agent_name_in_from']);
        });

        Schema::table('email_inboxes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('aliases');
        });

        Schema::table('helpdesk_settings', function (Blueprint $table) {
            $table->dropColumn([
                'email_allow_agent_initiated',
                'email_use_agent_name_in_from',
                'email_automatic_bcc',
                'email_reply_to_address',
                'email_use_reply_to_as_requester',
                'email_use_original_sender_for_forwarded',
                'email_flexible_recipients',
                'email_ignore_ticket_id_threading',
                'email_create_ticket_on_subject_change',
                'email_detect_auto_replies',
            ]);
        });
    }
};
