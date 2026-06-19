<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_settings', function (Blueprint $table) {
            $table->boolean('sync_ticket_status_from_external_issues')->default(false)->after('email_blocklist');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_settings', function (Blueprint $table) {
            $table->dropColumn('sync_ticket_status_from_external_issues');
        });
    }
};
