<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_settings', function (Blueprint $table) {
            $table->json('kb_locales')->nullable()->after('kb_deflection_enabled');
            $table->string('kb_default_locale', 10)->default('en')->after('kb_locales');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_settings', function (Blueprint $table) {
            $table->dropColumn(['kb_locales', 'kb_default_locale']);
        });
    }
};
