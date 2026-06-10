<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_settings', function (Blueprint $table) {
            $table->boolean('sso_enabled')->default(false)->after('closed_ticket_retention_days');
            $table->string('sso_protocol')->nullable()->after('sso_enabled');
            $table->text('sso_config')->nullable()->after('sso_protocol');
        });
    }

    public function down(): void
    {
        Schema::table('security_settings', function (Blueprint $table) {
            $table->dropColumn(['sso_enabled', 'sso_protocol', 'sso_config']);
        });
    }
};
