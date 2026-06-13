<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->table('central_settings', function (Blueprint $table) {
            if (! Schema::connection('central')->hasColumn('central_settings', 'social_links')) {
                $table->json('social_links')->nullable()->after('currency');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('central_settings', function (Blueprint $table) {
            if (Schema::connection('central')->hasColumn('central_settings', 'social_links')) {
                $table->dropColumn('social_links');
            }
        });
    }
};
