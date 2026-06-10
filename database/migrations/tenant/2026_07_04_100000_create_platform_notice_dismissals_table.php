<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_notice_dismissals')) {
            return;
        }

        Schema::create('platform_notice_dismissals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('platform_notice_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('dismissed_at');

            $table->unique(['platform_notice_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_notice_dismissals');
    }
};
