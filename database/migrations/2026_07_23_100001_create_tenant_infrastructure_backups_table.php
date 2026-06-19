<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('tenant_infrastructure_backups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('object_key', 512);
            $table->string('label', 255)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamp('stored_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'object_key']);
            $table->index(['tenant_id', 'stored_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('tenant_infrastructure_backups');
    }
};
