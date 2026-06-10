<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('platform_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('tenant_name');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name');
            $table->string('user_email');
            $table->string('type');
            $table->string('subject');
            $table->text('body');
            $table->string('status')->default('open');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('platform_feedback');
    }
};
