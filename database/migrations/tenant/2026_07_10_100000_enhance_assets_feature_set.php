<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (! Schema::hasColumn('assets', 'manufacturer')) {
                $table->string('manufacturer')->nullable()->after('hostname');
            }

            if (! Schema::hasColumn('assets', 'model')) {
                $table->string('model')->nullable()->after('manufacturer');
            }

            if (! Schema::hasColumn('assets', 'vendor')) {
                $table->string('vendor')->nullable()->after('model');
            }

            if (! Schema::hasColumn('assets', 'purchase_cost')) {
                $table->decimal('purchase_cost', 12, 2)->nullable()->after('vendor');
            }

            if (! Schema::hasColumn('assets', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('purchase_cost');
            }

            if (! Schema::hasColumn('assets', 'discovery_source')) {
                $table->string('discovery_source')->nullable()->after('last_seen_at');
            }
        });

        Schema::create('asset_assignment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->timestamps();

            $table->index(['asset_id', 'created_at']);
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
        Schema::dropIfExists('asset_assignment_logs');

        Schema::table('assets', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('assets', 'manufacturer') ? 'manufacturer' : null,
                Schema::hasColumn('assets', 'model') ? 'model' : null,
                Schema::hasColumn('assets', 'vendor') ? 'vendor' : null,
                Schema::hasColumn('assets', 'purchase_cost') ? 'purchase_cost' : null,
                Schema::hasColumn('assets', 'last_seen_at') ? 'last_seen_at' : null,
                Schema::hasColumn('assets', 'discovery_source') ? 'discovery_source' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
