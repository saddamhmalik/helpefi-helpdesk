<?php

namespace Tests\Feature;

use App\Domains\Channels\Models\Channel;
use App\Domains\Tenancy\Models\TenantRouteMapping;
use App\Domains\Tenancy\Services\TenantRouteRegistryService;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class TenancyRouteTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class, SlaSeeder::class]);
        app(TenantRouteRegistryService::class)->syncCurrentTenant();
    }

    public function test_central_domain_resolves_widget_key_to_tenant(): void
    {
        $widgetKey = Channel::query()->where('slug', 'chat')->firstOrFail()->settings['widget_key'];
        tenancy()->end();

        $this->getJson('http://'.config('tenancy.central_app_domain').'/api/v1/chat/config', [
            'X-Widget-Key' => $widgetKey,
        ])
            ->assertOk()
            ->assertJsonStructure(['online', 'greeting']);
    }

    public function test_central_domain_rejects_unknown_widget_key(): void
    {
        tenancy()->end();

        $this->getJson('http://'.config('tenancy.central_app_domain').'/api/v1/chat/config', [
            'X-Widget-Key' => 'invalid-widget-key',
        ])->assertNotFound();
    }

    public function test_central_domain_resolves_inbound_email_token(): void
    {
        tenancy()->end();

        $token = config('helpdesk.inbound_email_token') ?: 'dev-inbound-token';

        $this->postJson('http://'.config('tenancy.central_app_domain').'/api/v1/channels/inbound/email', [
            'from_email' => 'visitor@example.com',
            'subject' => 'Central inbound test',
            'body' => 'Hello from central domain',
        ], [
            'X-Channel-Token' => $token,
        ])->assertCreated();
    }

    public function test_tenant_route_mapping_is_stored_on_central_connection(): void
    {
        $channel = Channel::query()->where('slug', 'chat')->firstOrFail();

        $this->assertDatabaseHas('tenant_route_mappings', [
            'tenant_id' => tenant('id'),
            'type' => TenantRouteMapping::TYPE_WIDGET_KEY,
            'lookup_key' => $channel->settings['widget_key'],
        ], 'central');
    }
}
