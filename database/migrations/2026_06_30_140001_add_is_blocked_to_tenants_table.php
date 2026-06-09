<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenants') && ! Schema::hasColumn('tenants', 'is_blocked')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->boolean('is_blocked')->default(false)->after('slug');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'is_blocked')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('is_blocked');
            });
        }
    }
};
