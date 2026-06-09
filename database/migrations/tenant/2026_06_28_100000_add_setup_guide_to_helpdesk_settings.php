<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_settings', function (Blueprint $table) {
            $table->timestamp('setup_completed_at')->nullable()->after('kb_deflection_enabled');
            $table->json('setup_steps_completed')->nullable()->after('setup_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_settings', function (Blueprint $table) {
            $table->dropColumn(['setup_completed_at', 'setup_steps_completed']);
        });
    }
};
