<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('helpdesk_settings')) {
            return;
        }

        Schema::table('helpdesk_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('helpdesk_settings', 'dummy_data_active')) {
                $table->boolean('dummy_data_active')->default(false)->after('setup_steps_completed');
            }

            if (! Schema::hasColumn('helpdesk_settings', 'dummy_data_choice_at')) {
                $table->timestamp('dummy_data_choice_at')->nullable()->after('dummy_data_active');
            }

            if (! Schema::hasColumn('helpdesk_settings', 'dummy_data_manifest')) {
                $table->json('dummy_data_manifest')->nullable()->after('dummy_data_choice_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('helpdesk_settings')) {
            return;
        }

        Schema::table('helpdesk_settings', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('helpdesk_settings', 'dummy_data_active') ? 'dummy_data_active' : null,
                Schema::hasColumn('helpdesk_settings', 'dummy_data_choice_at') ? 'dummy_data_choice_at' : null,
                Schema::hasColumn('helpdesk_settings', 'dummy_data_manifest') ? 'dummy_data_manifest' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
