<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_views', function (Blueprint $table) {
            $table->string('visibility', 20)->default('private')->after('name');
            $table->foreignId('team_id')->nullable()->after('visibility')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_views', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->dropColumn('visibility');
        });
    }
};
