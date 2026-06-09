<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_inboxes', function (Blueprint $table) {
            $table->string('oauth_provider')->nullable()->after('poll_error');
            $table->text('oauth_access_token')->nullable()->after('oauth_provider');
            $table->text('oauth_refresh_token')->nullable()->after('oauth_access_token');
            $table->timestamp('oauth_token_expires_at')->nullable()->after('oauth_refresh_token');
            $table->string('oauth_connected_email')->nullable()->after('oauth_token_expires_at');
            $table->json('oauth_metadata')->nullable()->after('oauth_connected_email');
        });
    }

    public function down(): void
    {
        Schema::table('email_inboxes', function (Blueprint $table) {
            $table->dropColumn([
                'oauth_provider',
                'oauth_access_token',
                'oauth_refresh_token',
                'oauth_token_expires_at',
                'oauth_connected_email',
                'oauth_metadata',
            ]);
        });
    }
};
