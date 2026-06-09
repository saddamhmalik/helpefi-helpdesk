<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('asset_discovery_devices');
        Schema::dropIfExists('asset_discovery_scans');
        Schema::dropIfExists('asset_software');
        Schema::dropIfExists('asset_hardware_profiles');
        Schema::dropIfExists('asset_agents');
        Schema::dropIfExists('asset_agent_enrollment_tokens');

        Schema::table('assets', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('assets', 'last_seen_at') ? 'last_seen_at' : null,
                Schema::hasColumn('assets', 'discovery_source') ? 'discovery_source' : null,
                Schema::hasColumn('assets', 'remote_enabled') ? 'remote_enabled' : null,
                Schema::hasColumn('assets', 'rdp_port') ? 'rdp_port' : null,
                Schema::hasColumn('assets', 'rdp_username') ? 'rdp_username' : null,
                Schema::hasColumn('assets', 'rdp_password') ? 'rdp_password' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
    }
};
