<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('central_settings') && ! Schema::hasColumn('central_settings', 'india_pricing')) {
            Schema::table('central_settings', function (Blueprint $table) {
                $table->boolean('india_pricing')->default(false)->after('currency');
            });
        }

        if (Schema::hasTable('subscriptions') && ! Schema::hasColumn('subscriptions', 'currency')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->string('currency', 3)->nullable()->after('billing_interval');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('central_settings') && Schema::hasColumn('central_settings', 'india_pricing')) {
            Schema::table('central_settings', function (Blueprint $table) {
                $table->dropColumn('india_pricing');
            });
        }

        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'currency')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }
    }
};
