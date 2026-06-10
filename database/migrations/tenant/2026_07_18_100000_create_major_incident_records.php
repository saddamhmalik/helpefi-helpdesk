<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('major_incident_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained('tickets')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->foreignId('declared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('declared_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->json('coordinator_user_ids')->nullable();
            $table->text('war_room_notes')->nullable();
            $table->text('summary')->nullable();
            $table->text('timeline')->nullable();
            $table->text('lessons_learned')->nullable();
            $table->text('action_items')->nullable();
            $table->timestamp('review_completed_at')->nullable();
            $table->foreignId('review_completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('declared_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('major_incident_records');
    }
};
