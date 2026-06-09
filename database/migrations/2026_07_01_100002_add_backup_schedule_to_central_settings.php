<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('central_settings')) {
            return;
        }

        Schema::table('central_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('central_settings', 'backup_schedule_enabled')) {
                $table->boolean('backup_schedule_enabled')->default(false)->after('plan_catalog');
            }

            if (! Schema::hasColumn('central_settings', 'backup_schedule_frequency')) {
                $table->string('backup_schedule_frequency', 16)->default('daily')->after('backup_schedule_enabled');
            }

            if (! Schema::hasColumn('central_settings', 'backup_schedule_weekday')) {
                $table->unsignedTinyInteger('backup_schedule_weekday')->default(1)->after('backup_schedule_frequency');
            }

            if (! Schema::hasColumn('central_settings', 'backup_schedule_time')) {
                $table->string('backup_schedule_time', 5)->default('02:00')->after('backup_schedule_weekday');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('central_settings')) {
            return;
        }

        Schema::table('central_settings', function (Blueprint $table) {
            $columns = [
                'backup_schedule_enabled',
                'backup_schedule_frequency',
                'backup_schedule_weekday',
                'backup_schedule_time',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('central_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
