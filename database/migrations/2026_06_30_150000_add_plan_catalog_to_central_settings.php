<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('central_settings')) {
            return;
        }

        if (! Schema::hasColumn('central_settings', 'plan_catalog')) {
            Schema::table('central_settings', function (Blueprint $table) {
                $table->json('plan_catalog')->nullable()->after('currency');
            });
        }

        if (! Schema::hasColumn('central_settings', 'addon_catalog')) {
            Schema::table('central_settings', function (Blueprint $table) {
                $table->json('addon_catalog')->nullable()->after('plan_catalog');
            });
        }

        $row = DB::table('central_settings')->first();

        if (! $row || $row->plan_catalog) {
            return;
        }

        $legacyPricing = $row->plan_pricing ? json_decode($row->plan_pricing, true) : null;
        $catalog = \App\Domains\Tenancy\Support\PlanCatalogDefinition::catalogFromLegacyPricing($legacyPricing);

        DB::table('central_settings')->update([
            'plan_catalog' => json_encode($catalog),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('central_settings') && Schema::hasColumn('central_settings', 'plan_catalog')) {
            Schema::table('central_settings', function (Blueprint $table) {
                $table->dropColumn('plan_catalog');
            });
        }
    }
};
