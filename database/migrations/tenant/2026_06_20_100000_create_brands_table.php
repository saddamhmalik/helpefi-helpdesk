<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('brands')) {
            Schema::create('brands', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->string('portal_title')->nullable();
                $table->string('primary_color', 20)->nullable();
                $table->string('accent_color', 20)->nullable();
                $table->string('ticket_number_prefix', 20)->nullable();
                $table->json('ticket_fields')->nullable();
                $table->foreignId('default_ticket_priority_id')->nullable()->constrained('ticket_priorities')->nullOnDelete();
                $table->boolean('kb_deflection_enabled')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('email_inboxes') && ! Schema::hasColumn('email_inboxes', 'brand_id')) {
            Schema::table('email_inboxes', function (Blueprint $table) {
                $table->foreignId('brand_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        if (Schema::hasTable('knowledge_collections') && ! Schema::hasColumn('knowledge_collections', 'brand_id')) {
            Schema::table('knowledge_collections', function (Blueprint $table) {
                $table->foreignId('brand_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        if (Schema::hasTable('tickets') && ! Schema::hasColumn('tickets', 'brand_id')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->foreignId('brand_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        if (DB::table('brands')->doesntExist()) {
            $brandId = DB::table('brands')->insertGetId([
                'name' => 'Default',
                'slug' => 'default',
                'is_default' => true,
                'is_active' => true,
                'portal_title' => 'Help Center',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (Schema::hasTable('email_inboxes') && Schema::hasColumn('email_inboxes', 'brand_id')) {
                DB::table('email_inboxes')->update(['brand_id' => $brandId]);
            }

            if (Schema::hasTable('knowledge_collections') && Schema::hasColumn('knowledge_collections', 'brand_id')) {
                DB::table('knowledge_collections')->update(['brand_id' => $brandId]);
            }

            if (Schema::hasTable('tickets') && Schema::hasColumn('tickets', 'brand_id')) {
                DB::table('tickets')->update(['brand_id' => $brandId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });

        Schema::table('knowledge_collections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });

        Schema::table('email_inboxes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });

        Schema::dropIfExists('brands');
    }
};
