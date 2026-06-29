<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('central')->hasTable('marketing_seo_metadata')) {
            return;
        }

        Schema::connection('central')->create('marketing_seo_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('page_key', 180)->unique();

            $table->string('manual_seo_title', 200)->nullable();
            $table->string('manual_meta_description', 320)->nullable();
            $table->string('manual_keywords', 500)->nullable();
            $table->string('manual_og_description', 320)->nullable();
            $table->string('manual_twitter_description', 320)->nullable();

            $table->string('ai_seo_title', 200)->nullable();
            $table->string('ai_meta_description', 320)->nullable();
            $table->string('ai_keywords', 500)->nullable();
            $table->string('ai_og_description', 320)->nullable();
            $table->string('ai_twitter_description', 320)->nullable();
            $table->json('ai_slug_suggestions')->nullable();

            $table->longText('source_content')->nullable();
            $table->string('ai_source', 60)->nullable();
            $table->timestamp('ai_generated_at')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('marketing_seo_metadata');
    }
};

