<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('central')->hasTable('platform_testimonials')) {
            Schema::connection('central')->create('platform_testimonials', function (Blueprint $table) {
                $table->id();
                $table->text('quote');
                $table->string('name');
                $table->string('role');
                $table->string('company_type');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();

                $table->index(['is_enabled', 'sort_order']);
            });

            $now = now();

            DB::connection('central')->table('platform_testimonials')->insert([
                [
                    'quote' => 'We replaced email threads and a separate KB in the first week. Agents finally work from one inbox with SLA timers that actually match our hours.',
                    'name' => 'Priya S.',
                    'role' => 'Head of Support',
                    'company_type' => 'D2C brand',
                    'sort_order' => 1,
                    'is_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'quote' => 'Live chat plus the portal deflected a third of repeat questions before they became tickets. Setup during the trial was straightforward.',
                    'name' => 'Marcus T.',
                    'role' => 'IT Operations Lead',
                    'company_type' => 'B2B SaaS',
                    'sort_order' => 2,
                    'is_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'quote' => 'Multi-brand support for client portals without juggling logins — that alone paid for the switch from our old helpdesk.',
                    'name' => 'Elena R.',
                    'role' => 'Client Services Director',
                    'company_type' => 'Digital agency',
                    'sort_order' => 3,
                    'is_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        if (Schema::connection('central')->hasTable('central_settings')
            && ! Schema::connection('central')->hasColumn('central_settings', 'testimonials_enabled')) {
            Schema::connection('central')->table('central_settings', function (Blueprint $table) {
                $table->boolean('testimonials_enabled')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('central')->hasTable('central_settings')
            && Schema::connection('central')->hasColumn('central_settings', 'testimonials_enabled')) {
            Schema::connection('central')->table('central_settings', function (Blueprint $table) {
                $table->dropColumn('testimonials_enabled');
            });
        }
    }
};
