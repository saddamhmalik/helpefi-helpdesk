<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('tenant_infrastructure', function (Blueprint $table) {
            $table->string('backup_export_status', 32)->nullable()->after('storage_migration_status');
            $table->string('backup_export_path', 512)->nullable()->after('backup_export_status');
            $table->text('backup_export_message')->nullable()->after('backup_export_path');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('tenant_infrastructure', function (Blueprint $table) {
            $table->dropColumn(['backup_export_status', 'backup_export_path', 'backup_export_message']);
        });
    }
};
