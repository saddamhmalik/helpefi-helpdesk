<?php

namespace Tests\Feature;

use App\Domains\Channels\Models\Channel;
use App\Domains\Chat\Services\ChatAvailabilityService;
use App\Domains\Sla\Models\BusinessHours;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\SlaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ChatAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function chatChannel(): Channel
    {
        return Channel::query()->where('slug', 'chat')->firstOrFail();
    }

    public function test_always_online_mode_is_online(): void
    {
        $this->seed(ChannelSeeder::class);

        $channel = $this->chatChannel();
        $channel->update(['settings' => array_merge($channel->settings, ['offline_mode' => 'never'])]);

        $status = app(ChatAvailabilityService::class)->status($channel->fresh());

        $this->assertTrue($status['online']);
    }

    public function test_business_hours_mode_is_offline_outside_schedule(): void
    {
        $this->seed([ChannelSeeder::class, SlaSeeder::class]);

        $channel = $this->chatChannel();
        $hours = BusinessHours::query()->firstOrFail();

        Carbon::setTestNow(Carbon::parse('2026-06-07 08:00', $hours->timezone));

        $status = app(ChatAvailabilityService::class)->status($channel);

        $this->assertFalse($status['online']);
        $this->assertStringContainsString('Outside', $status['reason']);

        Carbon::setTestNow();
    }

    public function test_channels_settings_page_includes_chat_availability(): void
    {
        $this->seed(ChannelSeeder::class);

        $admin = \App\Models\User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/settings/channels')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Channels')
                ->has('chatAvailability.online')
                ->has('chatAvailability.reason'));
    }
}
