<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('marketing_page_views', function (Blueprint $table) {
            $table->id();
            $table->string('path', 2048);
            $table->string('referrer_host')->nullable();
            $table->string('visitor_hash', 64)->index();
            $table->boolean('is_bot')->default(false)->index();
            $table->timestamp('visited_at')->index();

            $table->index(['is_bot', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('marketing_page_views');
    }
};
