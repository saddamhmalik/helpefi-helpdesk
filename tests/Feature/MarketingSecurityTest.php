<?php

namespace Tests\Feature;

use App\Domains\Platform\Models\PlatformTestimonial;
use App\Models\PlatformUser;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function centralUrl(string $path = '/'): string
    {
        return 'http://'.config('tenancy.central_app_domain').$path;
    }

    public function test_marketing_pages_include_security_headers(): void
    {
        $response = $this->get($this->centralUrl('/'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy');
        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-site');
    }

    public function test_marketing_security_headers_apply_to_blog_and_landings(): void
    {
        foreach (['/blog', '/helpdesk-for-ecommerce', '/compare/freshdesk-vs-helpefi', '/ai-agent', '/integrations/slack', '/pricing'] as $path) {
            $response = $this->get($this->centralUrl($path));

            $response->assertOk();
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('Content-Security-Policy');
        }
    }

    public function test_marketing_csp_allows_turnstile_when_configured(): void
    {
        config(['marketing_seo.turnstile.secret_key' => 'test-secret']);

        $response = $this->get($this->centralUrl('/contact'));

        $response->assertOk();

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://challenges.cloudflare.com', $csp);
        $this->assertStringContainsString('frame-src', $csp);
    }

    public function test_platform_admin_routes_do_not_get_marketing_csp(): void
    {
        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);

        $this->post($this->centralUrl('/admin/login'), [
            'email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'password' => PlatformUserSeeder::DEFAULT_PASSWORD,
        ]);

        $response = $this->get($this->centralUrl('/admin/dashboard'));

        $response->assertOk();
        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function test_unauthenticated_users_cannot_manage_testimonials(): void
    {
        $this->post($this->centralUrl('/admin/testimonials'), [
            'quote' => 'Blocked quote',
            'name' => 'Blocked',
            'role' => 'Role',
            'company_type' => 'Type',
        ])->assertRedirect($this->centralUrl('/admin/login'));

        $this->put($this->centralUrl('/admin/testimonials/settings'), [
            'testimonials_enabled' => false,
        ])->assertRedirect($this->centralUrl('/admin/login'));
    }

    public function test_support_user_can_view_but_not_manage_testimonials(): void
    {
        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);

        PlatformUser::query()->create([
            'name' => 'Support User',
            'email' => 'support@helpdesk.test',
            'password' => 'password123',
            'is_active' => true,
        ])->syncRoles(['support']);

        $this->post($this->centralUrl('/admin/login'), [
            'email' => 'support@helpdesk.test',
            'password' => 'password123',
        ]);

        $this->get($this->centralUrl('/admin/testimonials'))->assertOk();
        $this->post($this->centralUrl('/admin/testimonials'), [
            'quote' => 'Should fail',
            'name' => 'Support',
            'role' => 'Role',
            'company_type' => 'Type',
        ])->assertForbidden();
        $this->put($this->centralUrl('/admin/testimonials/settings'), [
            'testimonials_enabled' => false,
        ])->assertForbidden();
    }

    public function test_testimonial_html_is_stripped_before_storage(): void
    {
        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);

        $this->post($this->centralUrl('/admin/login'), [
            'email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'password' => PlatformUserSeeder::DEFAULT_PASSWORD,
        ]);

        $this->post($this->centralUrl('/admin/testimonials'), [
            'quote' => '<strong>Safe quote text</strong>',
            'name' => '<b>Elena</b>',
            'role' => 'Director',
            'company_type' => 'Agency',
            'sort_order' => 1,
            'is_enabled' => true,
        ])->assertRedirect(route('central.admin.testimonials.index'));

        $testimonial = PlatformTestimonial::query()->where('name', 'Elena')->firstOrFail();

        $this->assertSame('Safe quote text', $testimonial->quote);
        $this->assertStringNotContainsString('<', $testimonial->quote);
        $this->assertStringNotContainsString('script', strtolower($testimonial->quote));
    }

    public function test_homepage_testimonials_do_not_include_script_tags(): void
    {
        PlatformTestimonial::query()->create([
            'quote' => 'Plain text only',
            'name' => 'Alex',
            'role' => 'Lead',
            'company_type' => 'SaaS',
            'sort_order' => 0,
            'is_enabled' => true,
        ]);

        $this->get($this->centralUrl('/'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('testimonials.0.quote', 'Plain text only')
                ->where('testimonials.0.name', 'Alex')
            );
    }

    public function test_platform_admin_login_is_rate_limited(): void
    {
        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post($this->centralUrl('/admin/login'), [
                'email' => 'wrong@helpdesk.test',
                'password' => 'wrong-password',
            ]);
        }

        $this->post($this->centralUrl('/admin/login'), [
            'email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'password' => PlatformUserSeeder::DEFAULT_PASSWORD,
        ])->assertStatus(429);
    }
}
