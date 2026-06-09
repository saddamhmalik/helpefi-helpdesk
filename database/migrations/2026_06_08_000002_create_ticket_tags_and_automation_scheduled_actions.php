<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_tag', function (Blueprint $table) {
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['ticket_id', 'tag_id']);
        });

        Schema::create('automation_scheduled_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->json('actions');
            $table->json('context')->nullable();
            $table->timestamp('run_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['run_at', 'processed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_scheduled_actions');
        Schema::dropIfExists('ticket_tag');
    }
};
