<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscriptions') && ! Schema::hasColumn('subscriptions', 'tenant_id')) {
            Schema::drop('subscriptions');
        }

        if (Schema::hasTable('subscriptions')) {
            return;
        }

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('plan');
            $table->string('billing_interval', 10)->default('month');
            $table->json('active_addons')->nullable();
            $table->json('stripe_addon_items')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('renews_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
