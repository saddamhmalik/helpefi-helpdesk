<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_settings', function (Blueprint $table) {
            $table->boolean('auto_first_response_enabled')->default(false)->after('user_fields');
            $table->text('auto_first_response_body')->nullable()->after('auto_first_response_enabled');
            $table->json('email_blocklist')->nullable()->after('auto_first_response_body');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_settings', function (Blueprint $table) {
            $table->dropColumn([
                'auto_first_response_enabled',
                'auto_first_response_body',
                'email_blocklist',
            ]);
        });
    }
};
