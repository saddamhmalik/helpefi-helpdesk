<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_inboxes', function (Blueprint $table) {
            $table->string('inbound_method')->default('webhook')->after('is_active');
        });

        DB::table('email_inboxes')
            ->where('poll_enabled', true)
            ->update(['inbound_method' => 'poll']);
    }

    public function down(): void
    {
        Schema::table('email_inboxes', function (Blueprint $table) {
            $table->dropColumn('inbound_method');
        });
    }
};
