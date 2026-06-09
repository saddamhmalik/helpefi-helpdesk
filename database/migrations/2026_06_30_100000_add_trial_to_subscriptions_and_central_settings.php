<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscriptions') && ! Schema::hasColumn('subscriptions', 'trial_ends_at')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->timestamp('trial_ends_at')->nullable()->after('status');
            });
        }

        if (! Schema::hasTable('central_settings')) {
            Schema::create('central_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('trial_days')->default(14);
                $table->timestamps();
            });

            DB::table('central_settings')->insert([
                'trial_days' => (int) config('billing.trial_days', 14),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'trial_ends_at')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn('trial_ends_at');
            });
        }

        Schema::dropIfExists('central_settings');
    }
};
