<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('tenant_infrastructure', function (Blueprint $table) {
            $table->unsignedSmallInteger('health_failure_count')->default(0)->after('status_message');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('tenant_infrastructure', function (Blueprint $table) {
            $table->dropColumn('health_failure_count');
        });
    }
};
