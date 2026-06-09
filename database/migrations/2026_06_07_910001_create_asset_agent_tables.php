<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_agent_enrollment_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('asset_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('hostname')->nullable();
            $table->string('platform', 32)->nullable();
            $table->string('agent_version', 32)->nullable();
            $table->string('status', 32)->default('online');
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('last_check_in_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('asset_hardware_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('os_name')->nullable();
            $table->string('os_version')->nullable();
            $table->string('platform', 32)->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('cpu_model')->nullable();
            $table->unsignedInteger('memory_mb')->nullable();
            $table->unsignedInteger('disk_total_gb')->nullable();
            $table->string('logged_in_user')->nullable();
            $table->json('network_interfaces')->nullable();
            $table->json('disks')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_software', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('version')->nullable();
            $table->string('publisher')->nullable();
            $table->date('installed_at')->nullable();
            $table->timestamps();

            $table->unique(['asset_id', 'name', 'publisher']);
            $table->index(['asset_id', 'name']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->boolean('remote_enabled')->default(false)->after('discovery_source');
            $table->unsignedSmallInteger('rdp_port')->default(3389)->after('remote_enabled');
            $table->text('rdp_username')->nullable()->after('rdp_port');
            $table->text('rdp_password')->nullable()->after('rdp_username');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['remote_enabled', 'rdp_port', 'rdp_username', 'rdp_password']);
        });

        Schema::dropIfExists('asset_software');
        Schema::dropIfExists('asset_hardware_profiles');
        Schema::dropIfExists('asset_agents');
        Schema::dropIfExists('asset_agent_enrollment_tokens');
    }
};
