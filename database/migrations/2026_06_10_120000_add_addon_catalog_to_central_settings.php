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

        if (! Schema::hasColumn('central_settings', 'addon_catalog')) {
            Schema::table('central_settings', function (Blueprint $table) {
                $table->json('addon_catalog')->nullable()->after('plan_catalog');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('central_settings')) {
            return;
        }

        if (Schema::hasColumn('central_settings', 'addon_catalog')) {
            Schema::table('central_settings', function (Blueprint $table) {
                $table->dropColumn('addon_catalog');
            });
        }
    }
};
