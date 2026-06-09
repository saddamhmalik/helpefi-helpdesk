<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_settings', function (Blueprint $table) {
            $table->json('ticket_fields')->nullable()->after('contact_fields');
            $table->json('user_fields')->nullable()->after('ticket_fields');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->json('custom_fields')->nullable()->after('description');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('custom_fields')->nullable()->after('contact_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('custom_fields');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('custom_fields');
        });

        Schema::table('helpdesk_settings', function (Blueprint $table) {
            $table->dropColumn(['ticket_fields', 'user_fields']);
        });
    }
};
