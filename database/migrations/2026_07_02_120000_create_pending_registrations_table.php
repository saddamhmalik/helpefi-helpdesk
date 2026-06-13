<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('pending_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('organization_name');
            $table->string('slug', 63);
            $table->string('admin_name');
            $table->string('admin_email');
            $table->string('password');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('admin_email');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('pending_registrations');
    }
};
