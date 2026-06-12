<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'stripe_id') && ! Schema::hasColumn('tenants', 'razorpay_customer_id')) {
                $table->renameColumn('stripe_id', 'razorpay_customer_id');
            }
        });

        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                if (Schema::hasColumn('subscriptions', 'stripe_subscription_id') && ! Schema::hasColumn('subscriptions', 'razorpay_subscription_id')) {
                    $table->renameColumn('stripe_subscription_id', 'razorpay_subscription_id');
                }

                if (Schema::hasColumn('subscriptions', 'stripe_price_id') && ! Schema::hasColumn('subscriptions', 'razorpay_plan_id')) {
                    $table->renameColumn('stripe_price_id', 'razorpay_plan_id');
                }

                if (Schema::hasColumn('subscriptions', 'stripe_addon_items') && ! Schema::hasColumn('subscriptions', 'razorpay_addon_items')) {
                    $table->renameColumn('stripe_addon_items', 'razorpay_addon_items');
                }
            });
        }

        if (Schema::hasTable('platform_payments')) {
            Schema::table('platform_payments', function (Blueprint $table) {
                if (Schema::hasColumn('platform_payments', 'stripe_invoice_id') && ! Schema::hasColumn('platform_payments', 'razorpay_payment_id')) {
                    $table->renameColumn('stripe_invoice_id', 'razorpay_payment_id');
                }

                if (Schema::hasColumn('platform_payments', 'stripe_customer_id') && ! Schema::hasColumn('platform_payments', 'razorpay_customer_id')) {
                    $table->renameColumn('stripe_customer_id', 'razorpay_customer_id');
                }

                if (Schema::hasColumn('platform_payments', 'stripe_subscription_id') && ! Schema::hasColumn('platform_payments', 'razorpay_subscription_id')) {
                    $table->renameColumn('stripe_subscription_id', 'razorpay_subscription_id');
                }

                if (Schema::hasColumn('platform_payments', 'stripe_payment_intent_id') && ! Schema::hasColumn('platform_payments', 'razorpay_order_id')) {
                    $table->renameColumn('stripe_payment_intent_id', 'razorpay_order_id');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'razorpay_customer_id') && ! Schema::hasColumn('tenants', 'stripe_id')) {
                $table->renameColumn('razorpay_customer_id', 'stripe_id');
            }
        });

        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                if (Schema::hasColumn('subscriptions', 'razorpay_subscription_id')) {
                    $table->renameColumn('razorpay_subscription_id', 'stripe_subscription_id');
                }

                if (Schema::hasColumn('subscriptions', 'razorpay_plan_id')) {
                    $table->renameColumn('razorpay_plan_id', 'stripe_price_id');
                }

                if (Schema::hasColumn('subscriptions', 'razorpay_addon_items')) {
                    $table->renameColumn('razorpay_addon_items', 'stripe_addon_items');
                }
            });
        }

        if (Schema::hasTable('platform_payments')) {
            Schema::table('platform_payments', function (Blueprint $table) {
                if (Schema::hasColumn('platform_payments', 'razorpay_payment_id')) {
                    $table->renameColumn('razorpay_payment_id', 'stripe_invoice_id');
                }

                if (Schema::hasColumn('platform_payments', 'razorpay_customer_id')) {
                    $table->renameColumn('razorpay_customer_id', 'stripe_customer_id');
                }

                if (Schema::hasColumn('platform_payments', 'razorpay_subscription_id')) {
                    $table->renameColumn('razorpay_subscription_id', 'stripe_subscription_id');
                }

                if (Schema::hasColumn('platform_payments', 'razorpay_order_id')) {
                    $table->renameColumn('razorpay_order_id', 'stripe_payment_intent_id');
                }
            });
        }
    }
};
