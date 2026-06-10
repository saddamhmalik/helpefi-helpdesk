<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscriptions')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'stripe_subscription_id')) {
                $table->string('stripe_subscription_id')->nullable()->unique()->after('renews_at');
            }
            if (! Schema::hasColumn('subscriptions', 'stripe_price_id')) {
                $table->string('stripe_price_id')->nullable()->after('stripe_subscription_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('subscriptions')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['stripe_subscription_id', 'stripe_price_id']);
        });
    }
};
