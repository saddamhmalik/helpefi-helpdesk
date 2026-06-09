<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('ip_address')->nullable()->after('location');
            $table->string('mac_address')->nullable()->after('ip_address');
            $table->string('hostname')->nullable()->after('mac_address');
            $table->timestamp('last_seen_at')->nullable()->after('hostname');
            $table->string('discovery_source')->nullable()->after('last_seen_at');

            $table->index('ip_address');
            $table->index('mac_address');
        });

        Schema::create('asset_discovery_scans', function (Blueprint $table) {
            $table->id();
            $table->string('subnet');
            $table->string('status')->default('pending');
            $table->unsignedInteger('devices_found')->default(0);
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_discovery_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_discovery_scan_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address');
            $table->string('mac_address')->nullable();
            $table->string('hostname')->nullable();
            $table->string('status')->default('new');
            $table->foreignId('matched_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignId('imported_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->timestamps();

            $table->unique(['asset_discovery_scan_id', 'ip_address'], 'ads_scan_ip_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_discovery_devices');
        Schema::dropIfExists('asset_discovery_scans');

        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['ip_address']);
            $table->dropIndex(['mac_address']);
            $table->dropColumn([
                'ip_address',
                'mac_address',
                'hostname',
                'last_seen_at',
                'discovery_source',
            ]);
        });
    }
};
