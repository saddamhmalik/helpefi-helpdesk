<?php

namespace Tests\Feature;

use App\Domains\Platform\Mail\PlatformTemplateMail;
use App\Domains\Platform\Models\PlatformEmailTemplate;
use Database\Seeders\PlatformEmailTemplateSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlatformEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlatformUserSeeder::class, PlatformEmailTemplateSeeder::class]);
    }

    public function test_registration_sends_confirmation_email_from_central(): void
    {
        Mail::fake();

        $this->post('http://'.config('tenancy.central_app_domain').'/register', [
            'organization_name' => 'Email Test Co',
            'slug' => 'email-test-co',
            'name' => 'Email Admin',
            'email' => 'email.admin@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertStatus(409);

        Mail::assertSent(PlatformTemplateMail::class, function (PlatformTemplateMail $mail) {
            return $mail->hasTo('email.admin@test.com');
        });
    }

    public function test_workspace_welcome_email_sent_after_provisioning(): void
    {
        Mail::fake();

        $this->post('http://'.config('tenancy.central_app_domain').'/register', [
            'organization_name' => 'Welcome Email Co',
            'slug' => 'welcome-email-co',
            'name' => 'Welcome Admin',
            'email' => 'welcome.admin@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        Mail::assertSent(PlatformTemplateMail::class, 2);

        Mail::assertSent(PlatformTemplateMail::class, function (PlatformTemplateMail $mail) {
            return $mail->hasTo('welcome.admin@test.com');
        });
    }

    public function test_platform_admin_can_manage_email_templates(): void
    {
        $this->post('http://'.config('tenancy.central_app_domain').'/admin/login', [
            'email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'password' => PlatformUserSeeder::DEFAULT_PASSWORD,
        ]);

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/emails')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Central/Admin/Emails/Index'));

        $this->post('http://'.config('tenancy.central_app_domain').'/admin/emails', [
            'name' => 'Trial ending reminder',
            'slug' => 'trial-ending-reminder',
            'subject' => 'Your trial ends soon',
            'body_html' => '<p>Hi {{admin_name}}, your trial ends soon.</p>',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('platform_email_templates', [
            'slug' => 'trial-ending-reminder',
        ], 'central');

        $template = PlatformEmailTemplate::query()->where('slug', 'trial-ending-reminder')->firstOrFail();

        $this->put('http://'.config('tenancy.central_app_domain').'/admin/emails/'.$template->id, [
            'name' => 'Trial ending reminder',
            'slug' => 'trial-ending-reminder',
            'subject' => 'Your {{brand}} trial ends soon',
            'body_html' => '<p>Updated body for {{admin_name}}</p>',
            'is_active' => true,
        ])->assertRedirect();

        $this->delete('http://'.config('tenancy.central_app_domain').'/admin/emails/'.$template->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('platform_email_templates', [
            'slug' => 'trial-ending-reminder',
        ], 'central');
    }

    public function test_system_email_templates_cannot_be_deleted(): void
    {
        $this->post('http://'.config('tenancy.central_app_domain').'/admin/login', [
            'email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'password' => PlatformUserSeeder::DEFAULT_PASSWORD,
        ]);

        $template = PlatformEmailTemplate::query()
            ->where('slug', PlatformEmailTemplate::SLUG_REGISTRATION)
            ->firstOrFail();

        $this->delete('http://'.config('tenancy.central_app_domain').'/admin/emails/'.$template->id)
            ->assertSessionHasErrors('slug');
    }
}
