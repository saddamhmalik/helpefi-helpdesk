<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('central_settings') && ! Schema::hasColumn('central_settings', 'plan_pricing')) {
            Schema::table('central_settings', function (Blueprint $table) {
                $table->json('plan_pricing')->nullable()->after('trial_days');
            });

            $pricing = collect(config('plans', []))
                ->map(fn (array $plan) => [
                    'name' => $plan['name'],
                    'price' => $plan['price'],
                ])
                ->all();

            DB::table('central_settings')->update([
                'plan_pricing' => json_encode($pricing),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('central_settings') && Schema::hasColumn('central_settings', 'plan_pricing')) {
            Schema::table('central_settings', function (Blueprint $table) {
                $table->dropColumn('plan_pricing');
            });
        }
    }
};
