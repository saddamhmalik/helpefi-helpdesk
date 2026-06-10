<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('change_records')) {
            Schema::create('change_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->unique()->constrained('tickets')->cascadeOnDelete();
                $table->string('risk', 20)->default('medium');
                $table->text('impact')->nullable();
                $table->text('rollback_plan')->nullable();
                $table->timestamp('planned_start')->nullable();
                $table->timestamp('planned_end')->nullable();
                $table->json('cab_user_ids')->nullable();
                $table->text('cab_notes')->nullable();
                $table->text('implementation_notes')->nullable();
                $table->timestamps();

                $table->index(['planned_start', 'planned_end']);
            });
        }

        if (! Schema::hasTable('problem_records')) {
            Schema::create('problem_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->unique()->constrained('tickets')->cascadeOnDelete();
                $table->text('root_cause')->nullable();
                $table->text('workaround')->nullable();
                $table->boolean('is_known_error')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('problem_incident_links')) {
            Schema::create('problem_incident_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('problem_ticket_id')->constrained('tickets')->cascadeOnDelete();
                $table->foreignId('incident_ticket_id')->constrained('tickets')->cascadeOnDelete();
                $table->foreignId('linked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['problem_ticket_id', 'incident_ticket_id'], 'problem_incident_links_pair_unique');
                $table->index('incident_ticket_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('problem_incident_links');
        Schema::dropIfExists('problem_records');
        Schema::dropIfExists('change_records');
    }
};
