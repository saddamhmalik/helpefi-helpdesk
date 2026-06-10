<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('subscriptions', 'active_addons')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->json('active_addons')->nullable()->after('billing_interval');
            });
        }

        if (! Schema::hasColumn('subscriptions', 'stripe_addon_items')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->json('stripe_addon_items')->nullable()->after('active_addons');
            });
        }
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'stripe_addon_items')) {
                $table->dropColumn('stripe_addon_items');
            }

            if (Schema::hasColumn('subscriptions', 'active_addons')) {
                $table->dropColumn('active_addons');
            }
        });
    }
};
