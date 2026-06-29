<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('central')->hasTable('marketing_content_drafts')) {
            return;
        }

        Schema::connection('central')->create('marketing_content_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('content_type', 40);
            $table->string('slug', 120)->nullable();
            $table->string('title', 200);
            $table->text('brief')->nullable();
            $table->string('target_page_key', 180)->nullable();
            $table->string('status', 20)->default('draft');
            $table->json('generated_content')->nullable();
            $table->json('edited_content')->nullable();
            $table->json('seo')->nullable();
            $table->json('schema_markup')->nullable();
            $table->json('internal_links')->nullable();
            $table->json('duplicate_warnings')->nullable();
            $table->string('content_fingerprint', 64)->nullable();
            $table->string('ai_source', 60)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('published_reference')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->timestamps();

            $table->index(['content_type', 'status']);
            $table->index('content_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('marketing_content_drafts');
    }
};
