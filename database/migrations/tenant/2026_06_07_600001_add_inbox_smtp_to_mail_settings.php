<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_settings', function (Blueprint $table) {
            $table->boolean('use_inbox_smtp')->default(false)->after('reply_enabled');
            $table->foreignId('email_inbox_id')->nullable()->after('use_inbox_smtp')->constrained('email_inboxes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mail_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('email_inbox_id');
            $table->dropColumn('use_inbox_smtp');
        });
    }
};
