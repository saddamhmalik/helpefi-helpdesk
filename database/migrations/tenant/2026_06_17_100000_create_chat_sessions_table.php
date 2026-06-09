<?php

use App\Domains\Channels\Models\Channel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('visitor_name')->nullable();
            $table->string('page_url', 2048)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['contact_id', 'channel_id', 'closed_at']);
        });

        Channel::query()->updateOrCreate(
            ['slug' => 'chat'],
            [
                'name' => 'Live chat',
                'type' => Channel::TYPE_CHAT,
                'is_active' => true,
                'settings' => [
                    'widget_key' => Str::random(32),
                    'greeting' => 'Hi! How can we help you today?',
                    'offline_message' => 'We are currently offline. Leave your email and message and we will get back to you.',
                    'offline_mode' => 'business_hours',
                    'allowed_origins' => ['*'],
                ],
            ],
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
        Channel::query()->where('slug', 'chat')->delete();
    }
};
