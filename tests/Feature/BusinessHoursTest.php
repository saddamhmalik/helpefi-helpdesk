<?php

namespace Tests\Feature;

use App\Domains\Channels\Models\Channel;
use App\Domains\Chat\Services\ChatAvailabilityService;
use App\Domains\Sla\Models\BusinessHours;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\SlaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BusinessHoursTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_business_hours_on_sla_settings(): void
    {
        $this->seed(SlaSeeder::class);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/settings/sla')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Sla')
                ->has('businessHours.id')
                ->has('businessHours.timezone')
                ->has('businessHours.schedule.mon')
                ->has('timezoneOptions')
                ->has('weekdays'));
    }

    public function test_admin_can_update_business_hours_and_timezone(): void
    {
        $this->seed(SlaSeeder::class);

        $admin = User::factory()->admin()->create();
        $hours = BusinessHours::query()->firstOrFail();

        $this->actingAs($admin)
            ->put("/settings/sla/business-hours/{$hours->id}", [
                'name' => 'APAC Support Hours',
                'timezone' => 'Asia/Karachi',
                'schedule' => [
                    'mon' => ['start' => '08:00', 'end' => '18:00'],
                    'tue' => ['start' => '08:00', 'end' => '18:00'],
                    'wed' => ['start' => '08:00', 'end' => '18:00'],
                    'thu' => ['start' => '08:00', 'end' => '18:00'],
                    'fri' => ['start' => '08:00', 'end' => '18:00'],
                    'sat' => null,
                    'sun' => null,
                ],
            ])
            ->assertRedirect();

        $hours->refresh();

        $this->assertSame('APAC Support Hours', $hours->name);
        $this->assertSame('Asia/Karachi', $hours->timezone);
        $this->assertSame('08:00', $hours->schedule['mon']['start']);
    }

    public function test_business_hours_update_rejects_invalid_window(): void
    {
        $this->seed(SlaSeeder::class);

        $admin = User::factory()->admin()->create();
        $hours = BusinessHours::query()->firstOrFail();

        $this->actingAs($admin)
            ->put("/settings/sla/business-hours/{$hours->id}", [
                'name' => 'Broken Hours',
                'timezone' => 'UTC',
                'schedule' => [
                    'mon' => ['start' => '17:00', 'end' => '09:00'],
                    'tue' => null,
                    'wed' => null,
                    'thu' => null,
                    'fri' => null,
                    'sat' => null,
                    'sun' => null,
                ],
            ])
            ->assertSessionHasErrors('schedule.mon.end');
    }

    public function test_chat_availability_uses_updated_business_hours(): void
    {
        $this->seed([SlaSeeder::class, ChannelSeeder::class]);

        $admin = User::factory()->admin()->create();
        $hours = BusinessHours::query()->firstOrFail();
        $channel = Channel::query()->where('slug', 'chat')->firstOrFail();
        $channel->update(['settings' => array_merge($channel->settings, ['offline_mode' => 'business_hours'])]);

        $this->actingAs($admin)
            ->put("/settings/sla/business-hours/{$hours->id}", [
                'name' => 'Extended Hours',
                'timezone' => 'UTC',
                'schedule' => [
                    'mon' => ['start' => '00:00', 'end' => '23:59'],
                    'tue' => ['start' => '00:00', 'end' => '23:59'],
                    'wed' => ['start' => '00:00', 'end' => '23:59'],
                    'thu' => ['start' => '00:00', 'end' => '23:59'],
                    'fri' => ['start' => '00:00', 'end' => '23:59'],
                    'sat' => ['start' => '00:00', 'end' => '23:59'],
                    'sun' => ['start' => '00:00', 'end' => '23:59'],
                ],
            ])
            ->assertRedirect();

        Carbon::setTestNow(Carbon::parse('2026-06-07 22:30', 'UTC'));

        $status = app(ChatAvailabilityService::class)->status($channel->fresh());

        $this->assertTrue($status['online']);

        Carbon::setTestNow();
    }
}
