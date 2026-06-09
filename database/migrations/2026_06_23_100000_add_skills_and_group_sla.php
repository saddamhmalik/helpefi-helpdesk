<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('skill_user', function (Blueprint $table) {
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['skill_id', 'user_id']);
        });

        Schema::table('assignment_rules', function (Blueprint $table) {
            $table->foreignId('ticket_priority_id')->nullable()->after('channel_ids')->constrained()->nullOnDelete();
            $table->json('skill_ids')->nullable()->after('ticket_priority_id');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('customer_tier')->nullable()->after('description');
        });

        Schema::table('sla_policies', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('business_hours_id')->constrained()->nullOnDelete();
            $table->string('customer_tier')->nullable()->after('team_id');

            $table->unique('team_id');
            $table->unique('customer_tier');
        });
    }

    public function down(): void
    {
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->dropUnique(['team_id']);
            $table->dropUnique(['customer_tier']);
            $table->dropConstrainedForeignId('team_id');
            $table->dropColumn('customer_tier');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('customer_tier');
        });

        Schema::table('assignment_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ticket_priority_id');
            $table->dropColumn('skill_ids');
        });

        Schema::dropIfExists('skill_user');
        Schema::dropIfExists('skills');
    }
};
