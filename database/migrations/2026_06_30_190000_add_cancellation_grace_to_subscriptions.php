<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('subscriptions', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('renews_at');
            $table->timestamp('access_ends_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'access_ends_at']);
        });
    }
};
