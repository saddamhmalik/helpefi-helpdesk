<?php

namespace Tests\Feature;

use App\Domains\Platform\Models\MarketingLead;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformMarketingLeadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);
    }

    private function centralUrl(string $path = '/'): string
    {
        return 'http://'.config('tenancy.central_app_domain').$path;
    }

    private function adminLogin(): void
    {
        $this->post($this->centralUrl('/admin/login'), [
            'email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'password' => PlatformUserSeeder::DEFAULT_PASSWORD,
        ]);
    }

    public function test_public_lead_capture_persists_homepage_submission(): void
    {
        $response = $this->postJson($this->centralUrl('/api/marketing/leads'), [
            'email' => 'demo@acme.test',
            'name' => 'Jane Admin',
            'company' => 'Acme Inc',
            'source' => 'homepage',
            'intent' => 'demo',
            'marketing_consent' => 1,
            'page_url' => 'https://helpefi.com/',
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('marketing_leads', [
            'email' => 'demo@acme.test',
            'source' => MarketingLead::SOURCE_HOMEPAGE,
            'intent' => 'demo',
            'status' => MarketingLead::STATUS_NEW,
        ], 'central');
    }

    public function test_public_lead_capture_requires_marketing_consent(): void
    {
        $this->postJson($this->centralUrl('/api/marketing/leads'), [
            'email' => 'demo@acme.test',
            'source' => 'homepage',
            'marketing_consent' => 0,
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('marketing_leads', [
            'email' => 'demo@acme.test',
        ], 'central');
    }

    public function test_public_lead_capture_stores_chatbot_transcript(): void
    {
        $this->postJson($this->centralUrl('/api/marketing/leads'), [
            'email' => 'chat@acme.test',
            'source' => 'chatbot',
            'intent' => 'chat',
            'marketing_consent' => 1,
            'chat_transcript' => [
                ['role' => 'user', 'text' => 'Do you support SSO?'],
                ['role' => 'assistant', 'text' => 'Yes, SSO is available on Pro plans.'],
            ],
        ])->assertOk();

        $lead = MarketingLead::query()->where('email', 'chat@acme.test')->firstOrFail();

        $this->assertSame(MarketingLead::SOURCE_CHATBOT, $lead->source);
        $this->assertCount(2, $lead->metadata['chat_transcript'] ?? []);
    }

    public function test_registration_creates_incomplete_signup_lead(): void
    {
        $this->post($this->centralUrl('/register'), [
            'organization_name' => 'Acme Support',
            'slug' => 'acme-lead-test',
            'name' => 'Jane Admin',
            'email' => 'register-lead@acme.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseHas('marketing_leads', [
            'email' => 'register-lead@acme.test',
            'source' => MarketingLead::SOURCE_REGISTRATION,
            'intent' => 'incomplete_signup',
        ], 'central');
    }

    public function test_platform_admin_can_view_leads_index(): void
    {
        MarketingLead::query()->create([
            'email' => 'lead@acme.test',
            'name' => 'Jane Admin',
            'source' => MarketingLead::SOURCE_HOMEPAGE,
            'intent' => 'demo',
            'status' => MarketingLead::STATUS_NEW,
        ]);

        $this->adminLogin();

        $this->get($this->centralUrl('/admin/leads'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Admin/Leads/Index')
                ->has('leads.data', 1));
    }

    public function test_platform_admin_can_update_lead_status(): void
    {
        $lead = MarketingLead::query()->create([
            'email' => 'lead@acme.test',
            'source' => MarketingLead::SOURCE_CONTACT,
            'intent' => 'sales',
            'status' => MarketingLead::STATUS_NEW,
        ]);

        $this->adminLogin();

        $this->put($this->centralUrl("/admin/leads/{$lead->id}/status"), [
            'status' => MarketingLead::STATUS_CONTACTED,
        ])->assertRedirect();

        $this->assertSame(
            MarketingLead::STATUS_CONTACTED,
            $lead->fresh()->status,
        );
    }

    public function test_public_lead_capture_rejects_invalid_source(): void
    {
        $this->postJson($this->centralUrl('/api/marketing/leads'), [
            'email' => 'demo@acme.test',
            'source' => 'sql-injection',
            'marketing_consent' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['source']);
    }

    public function test_platform_admin_leads_index_rejects_invalid_filters(): void
    {
        $this->adminLogin();

        $this->get($this->centralUrl('/admin/leads?status=invalid-status&consent=maybe'))
            ->assertSessionHasErrors(['status', 'consent']);
    }

    public function test_platform_admin_rejects_invalid_lead_status_update(): void
    {
        $lead = MarketingLead::query()->create([
            'email' => 'lead@acme.test',
            'source' => MarketingLead::SOURCE_CONTACT,
            'intent' => 'sales',
            'status' => MarketingLead::STATUS_NEW,
        ]);

        $this->adminLogin();

        $this->put($this->centralUrl("/admin/leads/{$lead->id}/status"), [
            'status' => 'injected-status',
        ])->assertSessionHasErrors('status');

        $this->assertSame(MarketingLead::STATUS_NEW, $lead->fresh()->status);
    }

    public function test_platform_admin_rejects_oversized_lead_notes(): void
    {
        $lead = MarketingLead::query()->create([
            'email' => 'lead@acme.test',
            'source' => MarketingLead::SOURCE_CONTACT,
            'intent' => 'sales',
            'status' => MarketingLead::STATUS_NEW,
        ]);

        $this->adminLogin();

        $this->put($this->centralUrl("/admin/leads/{$lead->id}/notes"), [
            'notes' => str_repeat('a', 5001),
        ])->assertSessionHasErrors('notes');
    }
}
