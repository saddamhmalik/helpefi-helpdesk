<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('marketing_leads', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('name')->nullable();
            $table->string('company')->nullable();
            $table->string('source', 32)->index();
            $table->string('intent', 32)->index();
            $table->string('status', 24)->default('new')->index();
            $table->string('topic')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('marketing_consent_at')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedBigInteger('pending_registration_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('marketing_leads');
    }
};
