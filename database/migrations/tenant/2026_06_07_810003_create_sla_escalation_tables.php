<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_escalation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sla_policy_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('breach_type', 32);
            $table->unsignedInteger('delay_minutes_after_breach')->default(0);
            $table->json('actions');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['sla_policy_id', 'level', 'breach_type'], 'sla_esc_policy_level_type_unique');
        });

        Schema::create('sla_escalation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_sla_timer_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('breach_type', 32);
            $table->json('actions_taken')->nullable();
            $table->timestamp('triggered_at');
            $table->timestamps();

            $table->unique(['ticket_sla_timer_id', 'level', 'breach_type'], 'sla_esc_log_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_escalation_logs');
        Schema::dropIfExists('sla_escalation_rules');
    }
};
