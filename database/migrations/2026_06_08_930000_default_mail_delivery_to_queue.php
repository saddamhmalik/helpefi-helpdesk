<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('mail_settings')) {
            return;
        }

        DB::table('mail_settings')->update(['delivery_mode' => 'queue']);
    }

    public function down(): void
    {
    }
};
