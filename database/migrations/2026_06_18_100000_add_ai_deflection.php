<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->boolean('deflection_enabled')->default(false)->after('model');
            $table->boolean('deflection_portal_enabled')->default(true)->after('deflection_enabled');
            $table->boolean('deflection_widget_enabled')->default(true)->after('deflection_portal_enabled');
        });

        Schema::create('ai_deflection_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_id');
            $table->string('channel');
            $table->string('event_type');
            $table->text('query')->nullable();
            $table->foreignId('article_id')->nullable()->constrained('knowledge_articles')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
            $table->index(['channel', 'event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_deflection_events');

        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn([
                'deflection_enabled',
                'deflection_portal_enabled',
                'deflection_widget_enabled',
            ]);
        });
    }
};
