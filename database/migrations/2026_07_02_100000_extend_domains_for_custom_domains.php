<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('domains')) {
            return;
        }

        Schema::table('domains', function (Blueprint $table) {
            if (! Schema::hasColumn('domains', 'type')) {
                $table->string('type', 16)->default('platform')->after('tenant_id');
            }

            if (! Schema::hasColumn('domains', 'is_primary')) {
                $table->boolean('is_primary')->default(false)->after('type');
            }

            if (! Schema::hasColumn('domains', 'verification_status')) {
                $table->string('verification_status', 16)->nullable()->after('is_primary');
            }

            if (! Schema::hasColumn('domains', 'verification_token')) {
                $table->string('verification_token', 64)->nullable()->after('verification_status');
            }

            if (! Schema::hasColumn('domains', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verification_token');
            }
        });

        if (Schema::hasTable('tenants') && ! Schema::hasColumn('tenants', 'custom_domain_redirect')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->boolean('custom_domain_redirect')->default(false)->after('is_blocked');
            });
        }

        DB::table('domains')->update([
            'type' => 'platform',
            'is_primary' => true,
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'custom_domain_redirect')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('custom_domain_redirect');
            });
        }

        if (! Schema::hasTable('domains')) {
            return;
        }

        Schema::table('domains', function (Blueprint $table) {
            foreach (['type', 'is_primary', 'verification_status', 'verification_token', 'verified_at'] as $column) {
                if (Schema::hasColumn('domains', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
