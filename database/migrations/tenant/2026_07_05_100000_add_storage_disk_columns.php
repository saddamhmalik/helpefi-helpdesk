<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_attachments', function (Blueprint $table) {
            $table->string('storage_disk', 32)->nullable()->after('path');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_disk', 32)->nullable()->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_attachments', function (Blueprint $table) {
            $table->dropColumn('storage_disk');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_disk');
        });
    }
};
