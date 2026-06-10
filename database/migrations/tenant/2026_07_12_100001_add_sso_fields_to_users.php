<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sso_subject')->nullable()->after('remember_token');
            $table->string('sso_provider')->nullable()->after('sso_subject');
            $table->index(['sso_provider', 'sso_subject']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['sso_provider', 'sso_subject']);
            $table->dropColumn(['sso_subject', 'sso_provider']);
        });
    }
};
