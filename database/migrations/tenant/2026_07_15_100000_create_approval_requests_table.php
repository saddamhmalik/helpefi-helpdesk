<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_catalog_items', function (Blueprint $table) {
            $table->boolean('requires_approval')->default(false)->after('is_active');
            $table->json('approver_user_ids')->nullable()->after('requires_approval');
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_catalog_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->string('status', 32)->default('pending');
            $table->unsignedSmallInteger('current_step')->default(1);
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requester_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('approval_request_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->foreignId('approver_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->unique(['approval_request_id', 'step_order']);
            $table->index(['approver_user_id', 'status']);
        });

        Schema::table('notification_settings', function (Blueprint $table) {
            $table->boolean('notify_approval_pending')->default(true)->after('notify_sla_breach');
        });
    }

    public function down(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropColumn('notify_approval_pending');
        });

        Schema::dropIfExists('approval_request_steps');
        Schema::dropIfExists('approval_requests');

        Schema::table('service_catalog_items', function (Blueprint $table) {
            $table->dropColumn(['requires_approval', 'approver_user_ids']);
        });
    }
};
