<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Integrations\Models\IntegrationConnection;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TenantBootstrapSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class Phase6P3ApiTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            TenantBootstrapSeeder::class,
            TicketLookupSeeder::class,
            ChannelSeeder::class,
            SlaSeeder::class,
        ]);

        Subscription::query()->updateOrCreate(
            ['tenant_id' => tenant('id')],
            [
                'plan' => 'enterprise',
                'status' => Subscription::STATUS_ACTIVE,
                'renews_at' => now()->addMonth(),
            ],
        );
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();
    }

    private function token(User $user): string
    {
        return $this->tenantPostJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('token');
    }

    public function test_openapi_spec_is_public(): void
    {
        $this->tenantGetJson('/api/v1/openapi.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.1.0')
            ->assertJsonPath('info.title', 'Helpdesk API');
    }

    public function test_api_docs_page_loads(): void
    {
        $this->tenantGet('/api/docs')->assertOk();
    }

    public function test_api_ticket_statuses_crud(): void
    {
        $token = $this->token($this->admin());

        $this->withToken($token)
            ->tenantPostJson('/api/v1/ticket-statuses', [
                'name' => 'On hold',
                'color' => 'amber',
                'is_closed' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('slug', 'on-hold');

        $this->withToken($token)
            ->tenantGetJson('/api/v1/ticket-statuses')
            ->assertOk()
            ->assertJsonFragment(['name' => 'On hold']);
    }

    public function test_api_snooze_via_workspace(): void
    {
        $status = TicketStatus::query()->where('slug', 'open')->firstOrFail();
        $priority = TicketPriority::query()->where('slug', 'normal')->firstOrFail();
        $contact = Contact::query()->create(['name' => 'API', 'email' => 'api@example.com']);
        $ticket = Ticket::query()->create([
            'subject' => 'API snooze',
            'contact_id' => $contact->id,
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $token = $this->token($this->admin());

        $this->withToken($token)
            ->tenantPostJson("/api/v1/workspace/tickets/{$ticket->id}/snooze", ['minutes' => 60])
            ->assertOk()
            ->assertJsonPath('snoozed_until', fn ($value) => $value !== null);
    }

    public function test_api_integration_connections(): void
    {
        $token = $this->token($this->admin());

        $this->withToken($token)
            ->tenantPutJson('/api/v1/integrations/connections/hubspot', [
                'access_token' => 'pat-test',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('provider', 'hubspot');

        $this->withToken($token)
            ->tenantGetJson('/api/v1/integrations/connections')
            ->assertOk()
            ->assertJsonFragment(['provider' => 'hubspot', 'is_active' => true]);

        $this->assertDatabaseHas('integration_connections', [
            'provider' => IntegrationConnection::PROVIDER_HUBSPOT,
            'is_active' => true,
        ]);
    }

    public function test_api_sso_settings(): void
    {
        $token = $this->token($this->admin());

        $this->withToken($token)
            ->tenantPutJson('/api/v1/security/sso', [
                'sso_enabled' => true,
                'sso_protocol' => 'oidc',
                'sso_config' => [
                    'preset' => 'google',
                    'client_id' => 'test-client',
                    'client_secret' => 'test-secret',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('sso_enabled', true);
    }
}
