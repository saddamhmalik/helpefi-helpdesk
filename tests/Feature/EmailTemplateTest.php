<?php

namespace Tests\Feature;

use App\Domains\Channels\Models\EmailTemplate;
use App\Domains\Channels\Services\EmailTemplateService;
use App\Models\User;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class EmailTemplateTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TicketLookupSeeder::class);
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();
    }

    public function test_admin_can_view_email_templates(): void
    {
        app(EmailTemplateService::class)->ensureDefaults();

        $this->actingAs($this->admin())
            ->tenantGet('/settings/email-templates')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/EmailTemplates/Index')
                ->has('templates', 12));
    }

    public function test_admin_can_update_email_template(): void
    {
        app(EmailTemplateService::class)->ensureDefaults();

        $template = EmailTemplate::query()->where('slug', EmailTemplate::SLUG_TEAM_INVITATION)->firstOrFail();

        $this->actingAs($this->admin())
            ->tenantPut("/settings/email-templates/{$template->id}", [
                'name' => 'Invite email',
                'subject' => 'Join {{app_name}} today',
                'body_html' => '<p>Hello from {{inviter_name}}</p>',
                'is_active' => true,
            ])
            ->assertRedirect('/settings/email-templates');

        $this->assertDatabaseHas('email_templates', [
            'id' => $template->id,
            'subject' => 'Join {{app_name}} today',
        ]);
    }

    public function test_render_replaces_placeholders(): void
    {
        app(EmailTemplateService::class)->ensureDefaults();

        $rendered = app(EmailTemplateService::class)->render(EmailTemplate::SLUG_TEAM_INVITATION, [
            'app_name' => 'Acme helpefi',
            'inviter_name' => 'Jane Admin',
            'role' => 'Agent',
            'accept_url' => 'https://example.test/invite',
            'expires_at' => 'Jan 1, 2027',
        ]);

        $this->assertStringContainsString('Acme helpefi', $rendered['subject']);
        $this->assertStringContainsString('Jane Admin', $rendered['body_html']);
    }
}
