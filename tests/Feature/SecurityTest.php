<?php

namespace Tests\Feature;

use App\Domains\Security\Models\AuditLog;
use App\Domains\Security\Models\SecuritySetting;
use App\Domains\Security\Support\Totp;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
        Role::findOrCreate('agent');
    }

    public function test_admin_can_view_security_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/settings/security')
            ->assertOk();
    }

    public function test_login_creates_audit_log(): void
    {
        $user = User::factory()->admin()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.login',
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_enable_two_factor_authentication(): void
    {
        $user = User::factory()->admin()->create();
        $totp = app(Totp::class);

        $this->actingAs($user)
            ->post('/settings/two-factor/setup')
            ->assertRedirect();

        $user->refresh();
        $secret = Crypt::decryptString($user->two_factor_secret);

        $this->actingAs($user)
            ->post('/settings/two-factor/confirm', [
                'code' => $totp->currentCode($secret),
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);
    }

    public function test_two_factor_login_requires_challenge(): void
    {
        $user = User::factory()->admin()->create();
        $totp = app(Totp::class);
        $secret = $totp->generateSecret();

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.challenge'));

        $this->post('/two-factor-challenge', [
            'code' => $totp->currentCode($secret),
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_retention_purge_removes_old_audit_logs(): void
    {
        AuditLog::query()->create([
            'event' => 'auth.login',
            'actor_email' => 'old@example.com',
            'created_at' => now()->subDays(120),
        ]);

        SecuritySetting::query()->create([
            'mfa_required_for_agents' => false,
            'audit_retention_days' => 90,
            'closed_ticket_retention_days' => null,
        ]);

        $this->artisan('security:purge-retention')->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', [
            'actor_email' => 'old@example.com',
        ]);
    }

    public function test_retention_purge_removes_old_closed_tickets(): void
    {
        $this->seed(TicketLookupSeeder::class);

        $closedStatus = TicketStatus::query()->where('slug', 'closed')->first()
            ?? TicketStatus::query()->create(['name' => 'Closed', 'slug' => 'closed', 'color' => '#000', 'sort_order' => 5, 'is_closed' => true]);
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        Ticket::query()->create([
            'number' => 'HD-90001',
            'subject' => 'Old closed ticket',
            'ticket_status_id' => $closedStatus->id,
            'ticket_priority_id' => $priority->id,
            'closed_at' => now()->subDays(400),
            'created_at' => now()->subDays(400),
        ]);

        SecuritySetting::query()->create([
            'mfa_required_for_agents' => false,
            'audit_retention_days' => 90,
            'closed_ticket_retention_days' => 365,
        ]);

        $this->artisan('security:purge-retention')->assertSuccessful();

        $this->assertDatabaseMissing('tickets', [
            'number' => 'HD-90001',
        ]);
    }

    public function test_api_security_snapshot_requires_admin(): void
    {
        $agent = User::factory()->create();
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $agent->email,
            'password' => 'password',
        ]);

        $this->withToken($login->json('token'))
            ->getJson('/api/v1/security')
            ->assertForbidden();
    }

    public function test_api_admin_can_fetch_security_snapshot(): void
    {
        $admin = User::factory()->admin()->create();
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->withToken($login->json('token'))
            ->getJson('/api/v1/security')
            ->assertOk()
            ->assertJsonStructure([
                'settings',
                'mfa_adoption',
                'audit_summary',
            ]);
    }
}
