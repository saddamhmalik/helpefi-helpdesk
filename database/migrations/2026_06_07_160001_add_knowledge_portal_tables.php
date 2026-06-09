<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_collections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        Schema::create('knowledge_article_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->timestamps();
        });

        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->foreignId('knowledge_collection_id')->nullable()->after('knowledge_category_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('knowledge_collection_id');
        });
        Schema::dropIfExists('knowledge_article_versions');
        Schema::dropIfExists('knowledge_collections');
    }
};
