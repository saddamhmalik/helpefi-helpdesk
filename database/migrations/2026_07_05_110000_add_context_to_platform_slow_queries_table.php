<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->table('platform_slow_queries', function (Blueprint $table) {
            $table->string('route_name')->nullable()->after('url');
            $table->string('source_file')->nullable()->after('route_name');
            $table->unsignedInteger('source_line')->nullable()->after('source_file');
            $table->string('source_callable')->nullable()->after('source_line');
            $table->string('database_host')->nullable()->after('connection');
            $table->string('database_name')->nullable()->after('database_host');

            $table->index('route_name');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('platform_slow_queries', function (Blueprint $table) {
            $table->dropIndex(['route_name']);
            $table->dropColumn([
                'route_name',
                'source_file',
                'source_line',
                'source_callable',
                'database_host',
                'database_name',
            ]);
        });
    }
};
