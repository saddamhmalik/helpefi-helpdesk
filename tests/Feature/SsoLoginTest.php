<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\Invitation;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Security\Services\SsoService;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TenantTestCase;

class SsoLoginTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            TenantBootstrapSeeder::class,
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

    private function identity(array $overrides = []): array
    {
        return array_merge([
            'subject' => 'google-subject-123',
            'email' => 'agent@acme.test',
            'name' => 'Agent User',
            'provider' => 'oidc:google',
        ], $overrides);
    }

    private function completeSsoLogin(array $overrides = []): User
    {
        $this->tenantGet('/login');

        return app(SsoService::class)->completeLogin($this->identity($overrides));
    }

    public function test_existing_agent_can_complete_sso_login(): void
    {
        $agent = User::factory()->create([
            'email' => 'agent@acme.test',
            'name' => 'Existing Agent',
        ]);
        $agent->assignRole('agent');

        $user = $this->completeSsoLogin();

        $this->assertTrue($user->is($agent));
        $this->assertSame('oidc:google', $user->sso_provider);
        $this->assertSame('google-subject-123', $user->sso_subject);
        $this->assertTrue(Auth::check());
    }

    public function test_existing_admin_can_complete_sso_login(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin.user@acme.test',
        ]);
        $admin->assignRole('admin');

        $user = $this->completeSsoLogin([
            'email' => 'admin.user@acme.test',
            'subject' => 'google-subject-admin',
        ]);

        $this->assertTrue($user->is($admin));
    }

    public function test_sso_matches_existing_user_case_insensitively(): void
    {
        $agent = User::factory()->create([
            'email' => 'agent@acme.test',
        ]);
        $agent->assignRole('agent');

        $user = $this->completeSsoLogin([
            'email' => 'Agent@ACME.test',
        ]);

        $this->assertTrue($user->is($agent));
    }

    public function test_customer_cannot_complete_sso_login(): void
    {
        $customer = User::factory()->create([
            'email' => 'customer@acme.test',
        ]);
        $customer->assignRole('customer');

        $this->tenantGet('/login');

        $this->expectException(ValidationException::class);

        app(SsoService::class)->completeLogin($this->identity([
            'email' => 'customer@acme.test',
        ]));
    }

    public function test_unknown_email_without_invitation_is_rejected(): void
    {
        $this->tenantGet('/login');

        $this->expectException(ValidationException::class);

        try {
            app(SsoService::class)->completeLogin($this->identity([
                'email' => 'unknown@acme.test',
            ]));
        } catch (ValidationException $exception) {
            $this->assertSame(
                __('messages.sso_no_agent_account'),
                $exception->errors()['email'][0],
            );

            throw $exception;
        }
    }

    public function test_pending_invitation_creates_agent_via_sso(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();

        Invitation::query()->create([
            'email' => 'newagent@acme.test',
            'token' => Invitation::generateToken(),
            'role' => 'agent',
            'invited_by' => $admin->id,
            'expires_at' => now()->addDays(7),
        ]);

        $user = $this->completeSsoLogin([
            'email' => 'NewAgent@acme.test',
            'name' => 'New Agent',
            'subject' => 'google-subject-new',
        ]);

        $this->assertSame('newagent@acme.test', $user->email);
        $this->assertTrue($user->hasRole('agent'));
        $this->assertSame('oidc:google', $user->sso_provider);
        $this->assertSame('google-subject-new', $user->sso_subject);
        $this->assertNotNull(Invitation::query()->where('email', 'newagent@acme.test')->value('accepted_at'));
    }

    public function test_expired_invitation_is_rejected(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();

        Invitation::query()->create([
            'email' => 'expired@acme.test',
            'token' => Invitation::generateToken(),
            'role' => 'agent',
            'invited_by' => $admin->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->tenantGet('/login');

        $this->expectException(ValidationException::class);

        app(SsoService::class)->completeLogin($this->identity([
            'email' => 'expired@acme.test',
        ]));
    }
}
