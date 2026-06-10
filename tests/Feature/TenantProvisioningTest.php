<?php

namespace Tests\Feature;

use App\Domains\Tenancy\Services\TenantProvisioningService;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Events\DatabaseCreated;
use Tests\TestCase;

class TenantProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_persists_admin_credentials_on_tenant(): void
    {
        $tenant = $this->provisionTenant('jane@persist.test', 'persist-test');

        $stored = json_decode(
            DB::connection('central')->table('tenants')->where('id', $tenant->id)->value('data'),
            true,
        );

        $this->assertSame('jane@persist.test', $stored['admin_email'] ?? null);
        $this->assertSame('Jane Admin', $stored['admin_name'] ?? null);
        $this->assertNull($stored['plan'] ?? null);

        tenancy()->initialize($tenant);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@persist.test',
            'name' => 'Jane Admin',
        ]);

        tenancy()->end();

        $tenant->delete();
    }

    public function test_provision_creates_domain_before_tenant_database_is_created(): void
    {
        $domainExistsBeforeDatabase = false;

        $this->app['events']->listen(DatabaseCreated::class, function (DatabaseCreated $event) use (&$domainExistsBeforeDatabase) {
            $domainExistsBeforeDatabase = $event->tenant->domains()->exists();
        });

        $tenant = $this->provisionTenant('order@domain.test', 'domain-order-test');

        $this->assertTrue($domainExistsBeforeDatabase);
        $this->assertSame(
            'domain-order-test.'.config('tenancy.central_app_domain'),
            $tenant->domains()->value('domain'),
        );

        $tenant->delete();
    }

    public function test_welcome_link_signs_in_registered_admin_email(): void
    {
        $provisioning = app(TenantProvisioningService::class);
        $tenant = $this->provisionTenant('owner@welcome.test', 'welcome-test');
        $welcomeUrl = $provisioning->welcomeUrl($tenant, 'owner@welcome.test');
        $domain = $tenant->domains()->value('domain');

        $this->get($welcomeUrl)
            ->assertRedirect('http://'.$domain.'/setup');

        tenancy()->initialize($tenant);
        $this->assertAuthenticatedAs(
            \App\Models\User::query()->where('email', 'owner@welcome.test')->firstOrFail()
        );
        tenancy()->end();

        $tenant->delete();
    }

    private function provisionTenant(string $email, string $slug): Tenant
    {
        return app(TenantProvisioningService::class)->provision(
            organizationName: 'Persist Test Co',
            slug: $slug,
            adminName: 'Jane Admin',
            adminEmail: $email,
            adminPassword: 'password123',
        );
    }
}
