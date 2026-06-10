<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->string('locale', 10)->default('en')->after('slug');
            $table->uuid('translation_group_id')->nullable()->after('locale');
        });

        DB::table('knowledge_articles')->orderBy('id')->lazy()->each(function (object $row) {
            DB::table('knowledge_articles')->where('id', $row->id)->update([
                'locale' => 'en',
                'translation_group_id' => (string) Str::uuid(),
            ]);
        });

        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['slug', 'locale']);
            $table->index('translation_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->dropUnique(['slug', 'locale']);
            $table->dropIndex(['translation_group_id']);
            $table->dropColumn(['locale', 'translation_group_id']);
            $table->unique('slug');
        });
    }
};
