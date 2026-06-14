<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_collections', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('is_public');
        });

        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });

        Schema::table('knowledge_collections', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
