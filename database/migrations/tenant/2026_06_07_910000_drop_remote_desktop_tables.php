<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('remote_session_recordings');
        Schema::dropIfExists('remote_session_events');
        Schema::dropIfExists('remote_sessions');
        Schema::dropIfExists('remote_connections');
    }

    public function down(): void
    {
    }
};
