<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('central')->hasTable('marketing_page_content')) {
            return;
        }

        Schema::connection('central')->create('marketing_page_content', function (Blueprint $table) {
            $table->id();
            $table->string('page_type', 40);
            $table->string('slug', 120);
            $table->json('content');
            $table->json('internal_links')->nullable();
            $table->string('page_key', 180)->nullable();
            $table->string('status', 20)->default('published');
            $table->foreignId('source_draft_id')->nullable()->constrained('marketing_content_drafts')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['page_type', 'slug']);
            $table->index('page_key');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('marketing_page_content');
    }
};
