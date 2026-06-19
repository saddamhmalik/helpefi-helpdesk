<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('tenant_infrastructure', function (Blueprint $table) {
            $table->boolean('auto_backup_enabled')->default(false)->after('backup_export_message');
            $table->string('auto_backup_frequency', 16)->nullable()->after('auto_backup_enabled');
            $table->unsignedTinyInteger('auto_backup_weekday')->nullable()->after('auto_backup_frequency');
            $table->string('auto_backup_time', 5)->nullable()->after('auto_backup_weekday');
            $table->timestamp('auto_backup_last_run_at')->nullable()->after('auto_backup_time');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('tenant_infrastructure', function (Blueprint $table) {
            $table->dropColumn([
                'auto_backup_enabled',
                'auto_backup_frequency',
                'auto_backup_weekday',
                'auto_backup_time',
                'auto_backup_last_run_at',
            ]);
        });
    }
};
