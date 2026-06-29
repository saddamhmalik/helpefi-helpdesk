<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('central')->hasTable('marketing_blog_posts')) {
            return;
        }

        Schema::connection('central')->table('marketing_blog_posts', function (Blueprint $table) {
            if (! Schema::connection('central')->hasColumn('marketing_blog_posts', 'category_slugs')) {
                $table->json('category_slugs')->nullable()->after('related_slugs');
            }

            if (! Schema::connection('central')->hasColumn('marketing_blog_posts', 'tag_slugs')) {
                $table->json('tag_slugs')->nullable()->after('category_slugs');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection('central')->hasTable('marketing_blog_posts')) {
            return;
        }

        Schema::connection('central')->table('marketing_blog_posts', function (Blueprint $table) {
            $table->dropColumn(['category_slugs', 'tag_slugs']);
        });
    }
};

