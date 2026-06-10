<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('central')->hasTable('subscriptions')) {
            return;
        }

        Schema::connection('central')->table('subscriptions', function (Blueprint $table) {
            if (! Schema::connection('central')->hasColumn('subscriptions', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('renews_at');
            }

            if (! Schema::connection('central')->hasColumn('subscriptions', 'access_ends_at')) {
                $table->timestamp('access_ends_at')->nullable()->after('cancelled_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection('central')->hasTable('subscriptions')) {
            return;
        }

        Schema::connection('central')->table('subscriptions', function (Blueprint $table) {
            if (Schema::connection('central')->hasColumn('subscriptions', 'access_ends_at')) {
                $table->dropColumn('access_ends_at');
            }

            if (Schema::connection('central')->hasColumn('subscriptions', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
        });
    }
};
