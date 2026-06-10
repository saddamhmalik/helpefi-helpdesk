<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('central')->hasTable('platform_notices')) {
            return;
        }

        Schema::connection('central')->create('platform_notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('body_html')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_disk')->default('local');
            $table->string('notice_type')->default('general');
            $table->string('target_scope')->default('all');
            $table->json('tenant_ids')->nullable();
            $table->string('audience')->default('admins');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('dismissible')->default(true);
            $table->string('priority')->default('normal');
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_active']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('platform_notices');
    }
};
