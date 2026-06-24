<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('central')->hasTable('trial_nurture_sends')) {
            return;
        }

        Schema::connection('central')->create('trial_nurture_sends', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('template_slug', 80);
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'template_slug']);
            $table->index('template_slug');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('trial_nurture_sends');
    }
};
