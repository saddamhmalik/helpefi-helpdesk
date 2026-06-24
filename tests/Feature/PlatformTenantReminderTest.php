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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformTenantReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlatformUserSeeder::class, PlatformEmailTemplateSeeder::class]);
    }

    public function test_platform_admin_can_view_lifecycle_email_status_and_resend(): void
    {
        Mail::fake();

        $tenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Reminder Co',
            'slug' => 'reminder-co-'.Str::random(6),
            'admin_name' => 'Reminder Admin',
            'admin_email' => 'reminder.admin@test.com',
            'created_at' => now()->subDays(2),
        ]);

        Subscription::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan' => 'professional',
                'status' => Subscription::STATUS_TRIAL,
                'trial_ends_at' => now()->addDays(10),
            ],
        );

        TrialNurtureSend::query()->create([
            'tenant_id' => $tenant->id,
            'template_slug' => PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_1,
            'sent_at' => now()->subDay(),
        ]);

        $this->post('http://'.config('tenancy.central_app_domain').'/admin/login', [
            'email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'password' => PlatformUserSeeder::DEFAULT_PASSWORD,
        ]);

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/tenants?q=reminder-co')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Admin/Tenants/Index')
                ->where('tenants.data.0.id', $tenant->id)
                ->has('tenants.data.0.lifecycle_emails.trial', 7)
                ->where('tenants.data.0.lifecycle_emails.trial.0.sent', true)
                ->where('tenants.data.0.lifecycle_emails.trial.0.slug', PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_1));

        $this->post('http://'.config('tenancy.central_app_domain')."/admin/tenants/{$tenant->id}/resend-lifecycle-email", [
            'template_slug' => PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_3,
        ])->assertRedirect();

        Mail::assertSent(PlatformTemplateMail::class, fn (PlatformTemplateMail $mail) => $mail->hasTo('reminder.admin@test.com'));

        $this->assertDatabaseHas('trial_nurture_sends', [
            'tenant_id' => $tenant->id,
            'template_slug' => PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_3,
        ], 'central');
    }

    public function test_resend_rejects_non_lifecycle_template_slug(): void
    {
        $tenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Reject Co',
            'slug' => 'reject-co-'.Str::random(6),
            'admin_email' => 'reject@test.com',
        ]);

        $this->post('http://'.config('tenancy.central_app_domain').'/admin/login', [
            'email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'password' => PlatformUserSeeder::DEFAULT_PASSWORD,
        ]);

        $this->post('http://'.config('tenancy.central_app_domain')."/admin/tenants/{$tenant->id}/resend-lifecycle-email", [
            'template_slug' => PlatformEmailTemplate::SLUG_REGISTRATION,
        ])->assertSessionHasErrors('template_slug');
    }
}
