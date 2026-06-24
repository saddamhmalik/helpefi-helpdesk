<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Platform\Mail\PlatformTemplateMail;
use App\Domains\Platform\Models\PlatformEmailTemplate;
use App\Domains\Platform\Models\TrialNurtureSend;
use App\Models\Tenant;
use Database\Seeders\PlatformEmailTemplateSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionEndingReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlatformUserSeeder::class, PlatformEmailTemplateSeeder::class]);
    }

    public function test_subscription_ending_command_sends_three_day_reminder_once(): void
    {
        Mail::fake();

        Carbon::setTestNow(Carbon::parse('2026-06-24 09:00:00'));

        $tenant = $this->createEndingTenant(daysUntilEnd: 3);

        $this->assertEquals(1, Tenant::query()->count());

        $sent = app(\App\Domains\Platform\Services\SubscriptionEndingReminderService::class)->dispatchDueEmails();

        $this->assertSame(1, $sent);
        $this->assertEquals(1, TrialNurtureSend::query()->count());
        Mail::assertSent(PlatformTemplateMail::class, fn (PlatformTemplateMail $mail) => $mail->hasTo('ending.admin@test.com'));

        $this->assertDatabaseHas('trial_nurture_sends', [
            'tenant_id' => $tenant->id,
            'template_slug' => PlatformEmailTemplate::SLUG_SUBSCRIPTION_ENDING_3_DAYS,
        ], 'central');

        $sentAgain = app(\App\Domains\Platform\Services\SubscriptionEndingReminderService::class)->dispatchDueEmails();

        $this->assertSame(0, $sentAgain);
        $this->assertEquals(1, TrialNurtureSend::query()->count());

        Carbon::setTestNow();
    }

    public function test_active_tenant_without_cancellation_does_not_receive_ending_reminder(): void
    {
        Mail::fake();

        $tenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Active Co',
            'slug' => 'active-co-'.Str::random(6),
            'admin_email' => 'active@test.com',
        ]);

        Subscription::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan' => 'professional',
                'status' => Subscription::STATUS_ACTIVE,
                'access_ends_at' => now()->addDays(3),
            ],
        );

        Artisan::call('platform:send-subscription-ending-reminders');

        $this->assertDatabaseMissing('trial_nurture_sends', [
            'tenant_id' => $tenant->id,
        ], 'central');
    }

    private function createEndingTenant(int $daysUntilEnd): Tenant
    {
        $tenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Ending Co',
            'slug' => 'ending-co-'.Str::random(6),
            'admin_name' => 'Ending Admin',
            'admin_email' => 'ending.admin@test.com',
        ]);

        Subscription::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan' => 'professional',
                'status' => Subscription::STATUS_ACTIVE,
                'cancelled_at' => now()->subDay(),
                'access_ends_at' => now()->addDays($daysUntilEnd)->endOfDay(),
            ],
        );

        return $tenant->fresh(['subscription']);
    }
}
