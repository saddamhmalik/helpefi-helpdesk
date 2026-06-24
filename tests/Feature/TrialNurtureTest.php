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
use Illuminate\Support\Str;
use Tests\TestCase;

class TrialNurtureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlatformUserSeeder::class, PlatformEmailTemplateSeeder::class]);
    }

    public function test_trial_nurture_command_sends_day_one_email_once(): void
    {
        Mail::fake();

        $tenant = $this->createTrialTenant(createdDaysAgo: 0);

        Artisan::call('platform:send-trial-nurture');

        Mail::assertSent(PlatformTemplateMail::class, fn (PlatformTemplateMail $mail) => $mail->hasTo('nurture.admin@trial.test'));
        $this->assertEquals(1, TrialNurtureSend::query()->where('tenant_id', $tenant->id)->count());

        Artisan::call('platform:send-trial-nurture');

        $this->assertEquals(1, TrialNurtureSend::query()->where('tenant_id', $tenant->id)->count());

        $this->assertDatabaseHas('trial_nurture_sends', [
            'tenant_id' => $tenant->id,
            'template_slug' => PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_1,
        ], 'central');
    }

    public function test_trial_nurture_sends_day_three_on_third_day(): void
    {
        Mail::fake();

        $tenant = $this->createTrialTenant(createdDaysAgo: 2);

        Artisan::call('platform:send-trial-nurture');

        Mail::assertSent(PlatformTemplateMail::class, fn (PlatformTemplateMail $mail) => $mail->hasTo('nurture.admin@trial.test'));

        $this->assertDatabaseHas('trial_nurture_sends', [
            'tenant_id' => $tenant->id,
            'template_slug' => PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_3,
        ], 'central');
    }

    public function test_expired_trial_tenant_does_not_receive_nurture(): void
    {
        Mail::fake();

        $tenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Expired Co',
            'slug' => 'expired-co-'.Str::random(6),
            'admin_name' => 'Expired Admin',
            'admin_email' => 'expired@trial.test',
            'created_at' => now()->subDays(3),
        ]);

        Subscription::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan' => 'professional',
                'status' => Subscription::STATUS_TRIAL,
                'trial_ends_at' => now()->subDay(),
            ],
        );

        Artisan::call('platform:send-trial-nurture');

        $this->assertDatabaseMissing('trial_nurture_sends', [
            'tenant_id' => $tenant->id,
        ], 'central');
    }

    private function createTrialTenant(int $createdDaysAgo): Tenant
    {
        $tenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Nurture Co',
            'slug' => 'nurture-co-'.Str::random(6),
            'admin_name' => 'Nurture Admin',
            'admin_email' => 'nurture.admin@trial.test',
            'created_at' => now()->subDays($createdDaysAgo),
        ]);

        Subscription::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan' => 'professional',
                'status' => Subscription::STATUS_TRIAL,
                'trial_ends_at' => now()->addDays(max(1, 14 - $createdDaysAgo)),
            ],
        );

        return $tenant->fresh();
    }
}
