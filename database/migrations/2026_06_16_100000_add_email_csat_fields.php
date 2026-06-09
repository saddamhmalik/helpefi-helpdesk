<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('csat_settings', function (Blueprint $table) {
            $table->boolean('email_enabled')->default(false)->after('comment_required');
        });

        Schema::table('csat_responses', function (Blueprint $table) {
            $table->string('channel')->default('portal')->after('comment');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('csat_email_sent_at')->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('csat_email_sent_at');
        });

        Schema::table('csat_responses', function (Blueprint $table) {
            $table->dropColumn('channel');
        });

        Schema::table('csat_settings', function (Blueprint $table) {
            $table->dropColumn('email_enabled');
        });
    }
};
