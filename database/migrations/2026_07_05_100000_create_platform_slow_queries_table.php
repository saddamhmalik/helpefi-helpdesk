<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('platform_slow_queries', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('connection', 64);
            $table->text('sql');
            $table->unsignedInteger('time_ms');
            $table->json('bindings')->nullable();
            $table->string('method', 16)->nullable();
            $table->text('url')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at', 'time_ms']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('platform_slow_queries');
    }
};
