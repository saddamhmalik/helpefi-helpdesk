<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('subscriptions', function (Blueprint $table) {
            if (! Schema::connection('central')->hasColumn('subscriptions', 'billing_interval')) {
                $table->string('billing_interval', 10)->default('month')->after('plan');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('subscriptions', function (Blueprint $table) {
            if (Schema::connection('central')->hasColumn('subscriptions', 'billing_interval')) {
                $table->dropColumn('billing_interval');
            }
        });
    }
};
