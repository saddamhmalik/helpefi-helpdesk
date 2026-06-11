<?php

namespace Tests\Feature;

use App\Domains\Auth\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
        Role::findOrCreate('agent');
        Role::findOrCreate('customer');
    }

    public function test_guest_can_view_forgot_password_page(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_agent_can_request_password_reset_link(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'agent@example.com']);
        $user->assignRole('agent');

        $this->post('/forgot-password', ['email' => 'agent@example.com'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(ResetPasswordMail::class, fn (ResetPasswordMail $mail) => $mail->hasTo('agent@example.com'));
    }

    public function test_customer_email_does_not_receive_reset_link(): void
    {
        Mail::fake();

        $user = User::factory()->customer()->create(['email' => 'customer@example.com']);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertNothingSent();
    }

    public function test_unknown_email_still_shows_success_message(): void
    {
        Mail::fake();

        $this->post('/forgot-password', ['email' => 'missing@example.com'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertNothingSent();
    }

    public function test_agent_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'agent@example.com']);
        $user->assignRole('agent');
        $token = Password::createToken($user);

        $this->get('/reset-password/'.$token.'?email='.urlencode($user->email))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/ResetPassword')
                ->where('email', $user->email));

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'new-password-123',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_reset_password_creates_audit_log(): void
    {
        $user = User::factory()->create(['email' => 'agent@example.com']);
        $user->assignRole('agent');
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.password_reset',
            'user_id' => $user->id,
        ]);
    }
}
