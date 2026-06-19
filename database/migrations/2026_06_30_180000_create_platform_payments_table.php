<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('platform_payments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('razorpay_payment_id')->unique();
            $table->string('razorpay_customer_id')->nullable()->index();
            $table->string('razorpay_subscription_id')->nullable();
            $table->string('razorpay_order_id')->nullable();
            $table->unsignedInteger('amount');
            $table->string('currency', 3);
            $table->string('status', 20)->default('paid');
            $table->string('plan')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('description')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('invoice_url')->nullable();
            $table->string('invoice_pdf')->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('platform_payments');
    }
};
