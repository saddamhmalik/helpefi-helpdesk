<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscriptions') && ! Schema::hasColumn('subscriptions', 'custom_amount')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->unsignedInteger('custom_amount')->nullable()->after('currency');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'custom_amount')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn('custom_amount');
            });
        }
    }
};
