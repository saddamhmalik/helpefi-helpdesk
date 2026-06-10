<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(false);
            $table->string('account_sid')->nullable();
            $table->text('auth_token')->nullable();
            $table->string('whatsapp_from')->nullable();
            $table->string('sms_from')->nullable();
            $table->string('webhook_token', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_settings');
    }
};
