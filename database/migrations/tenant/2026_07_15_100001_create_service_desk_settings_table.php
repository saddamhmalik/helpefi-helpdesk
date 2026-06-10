<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_desk_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('change_requires_approval')->default(false);
            $table->json('change_approver_user_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_desk_settings');
    }
};
