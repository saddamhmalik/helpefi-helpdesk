<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('release_version', 32)->nullable()->after('slug');
            $table->timestamp('release_upgraded_at')->nullable()->after('release_version');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['release_version', 'release_upgraded_at']);
        });
    }
};
