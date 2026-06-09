<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_inboxes', function (Blueprint $table) {
            $table->boolean('poll_enabled')->default(false)->after('is_active');
            $table->string('mailbox_provider')->nullable()->after('poll_enabled');
            $table->string('mailbox_protocol')->nullable()->after('mailbox_provider');
            $table->string('mailbox_host')->nullable()->after('mailbox_protocol');
            $table->unsignedSmallInteger('mailbox_port')->nullable()->after('mailbox_host');
            $table->string('mailbox_encryption')->nullable()->after('mailbox_port');
            $table->string('mailbox_username')->nullable()->after('mailbox_encryption');
            $table->text('mailbox_password')->nullable()->after('mailbox_username');
            $table->string('mailbox_folder')->default('INBOX')->after('mailbox_password');
            $table->json('mailbox_processed_ids')->nullable()->after('mailbox_folder');
            $table->timestamp('last_polled_at')->nullable()->after('mailbox_processed_ids');
            $table->text('poll_error')->nullable()->after('last_polled_at');
        });
    }

    public function down(): void
    {
        Schema::table('email_inboxes', function (Blueprint $table) {
            $table->dropColumn([
                'poll_enabled',
                'mailbox_provider',
                'mailbox_protocol',
                'mailbox_host',
                'mailbox_port',
                'mailbox_encryption',
                'mailbox_username',
                'mailbox_password',
                'mailbox_folder',
                'mailbox_processed_ids',
                'last_polled_at',
                'poll_error',
            ]);
        });
    }
};
