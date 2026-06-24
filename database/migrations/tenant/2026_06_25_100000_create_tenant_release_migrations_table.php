<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_release_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('release', 32);
            $table->string('step', 128);
            $table->unsignedInteger('batch');
            $table->timestamp('ran_at');
            $table->timestamps();

            $table->unique(['release', 'step']);
            $table->index('release');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_release_migrations');
    }
};
