<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_connections', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->text('config');
            $table->json('events')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_delivered_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_external_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('external_id');
            $table->string('external_key');
            $table->string('external_url');
            $table->string('status')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_id']);
            $table->index(['ticket_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_external_issues');
        Schema::dropIfExists('integration_connections');
    }
};
