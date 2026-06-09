<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_settings', function (Blueprint $table) {
            $table->boolean('kb_deflection_enabled')->default(true)->after('email_blocklist');
        });

        Schema::create('kb_deflection_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_id');
            $table->string('event_type');
            $table->text('query')->nullable();
            $table->foreignId('article_id')->nullable()->constrained('knowledge_articles')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_deflection_events');

        Schema::table('helpdesk_settings', function (Blueprint $table) {
            $table->dropColumn('kb_deflection_enabled');
        });
    }
};
