<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('central_settings') && ! Schema::hasColumn('central_settings', 'currency')) {
            Schema::table('central_settings', function (Blueprint $table) {
                $table->string('currency', 3)->default('USD')->after('trial_days');
            });

            DB::table('central_settings')->update([
                'currency' => strtoupper((string) config('billing.currency', 'USD')),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('central_settings') && Schema::hasColumn('central_settings', 'currency')) {
            Schema::table('central_settings', function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }
    }
};
