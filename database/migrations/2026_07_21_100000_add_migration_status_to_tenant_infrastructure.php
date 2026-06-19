<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_infrastructure', function (Blueprint $table) {
            $table->string('database_migration_status', 32)->nullable()->after('database_config');
            $table->string('storage_migration_status', 32)->nullable()->after('storage_config');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_infrastructure', function (Blueprint $table) {
            $table->dropColumn(['database_migration_status', 'storage_migration_status']);
        });
    }
};
