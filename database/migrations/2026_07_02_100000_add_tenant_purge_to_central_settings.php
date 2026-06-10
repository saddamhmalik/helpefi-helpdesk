<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('central_settings', function (Blueprint $table) {
            if (! Schema::connection('central')->hasColumn('central_settings', 'tenant_purge_grace_days')) {
                $table->unsignedSmallInteger('tenant_purge_grace_days')->default(15)->after('trial_days');
            }

            if (! Schema::connection('central')->hasColumn('central_settings', 'tenant_purge_enabled')) {
                $table->boolean('tenant_purge_enabled')->default(true)->after('tenant_purge_grace_days');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('central_settings', function (Blueprint $table) {
            if (Schema::connection('central')->hasColumn('central_settings', 'tenant_purge_enabled')) {
                $table->dropColumn('tenant_purge_enabled');
            }

            if (Schema::connection('central')->hasColumn('central_settings', 'tenant_purge_grace_days')) {
                $table->dropColumn('tenant_purge_grace_days');
            }
        });
    }
};
