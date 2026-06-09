<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_route_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('type', 32);
            $table->string('lookup_key');
            $table->timestamps();

            $table->unique(['type', 'lookup_key']);
            $table->index(['tenant_id', 'type']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_route_mappings');
    }
};
