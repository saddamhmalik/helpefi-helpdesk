<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saved_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('frequency');
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->unsignedTinyInteger('send_hour')->default(8);
            $table->string('format')->default('csv');
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();

            $table->unique('saved_report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
