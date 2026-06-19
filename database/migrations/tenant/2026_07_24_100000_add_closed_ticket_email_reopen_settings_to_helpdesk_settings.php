<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_settings', function (Blueprint $table) {
            $table->boolean('email_reopen_closed_on_inbound')->default(true)->after('email_detect_auto_replies');
            $table->boolean('email_suppress_reopen_on_thank_you')->default(true)->after('email_reopen_closed_on_inbound');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_settings', function (Blueprint $table) {
            $table->dropColumn([
                'email_reopen_closed_on_inbound',
                'email_suppress_reopen_on_thank_you',
            ]);
        });
    }
};
