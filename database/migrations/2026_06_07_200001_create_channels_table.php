<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type');
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('channel_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->foreignId('channel_id')->nullable()->after('ticket_id')->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('external_id')->nullable()->after('is_internal');
        });

        $now = now();
        DB::table('channels')->insert([
            ['name' => 'Web', 'slug' => 'web', 'type' => 'web', 'is_active' => true, 'settings' => json_encode([]), 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Customer portal', 'slug' => 'portal', 'type' => 'portal', 'is_active' => true, 'settings' => json_encode([]), 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Email', 'slug' => 'email', 'type' => 'email', 'is_active' => true, 'settings' => json_encode(['address' => 'support@helpdesk.test', 'inbound_token' => 'dev-inbound-token']), 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'API', 'slug' => 'api', 'type' => 'api', 'is_active' => true, 'settings' => json_encode([]), 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('channel_id');
            $table->dropConstrainedForeignId('contact_id');
            $table->dropColumn('external_id');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('channel_id');
        });

        Schema::dropIfExists('channels');
    }
};
