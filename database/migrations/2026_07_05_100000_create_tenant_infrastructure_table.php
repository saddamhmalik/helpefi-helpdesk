<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('tenant_infrastructure', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('database_mode', 32)->default('managed');
            $table->text('database_config')->nullable();
            $table->string('storage_mode', 32)->default('managed');
            $table->text('storage_config')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('status_message')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('tenant_infrastructure');
    }
};
