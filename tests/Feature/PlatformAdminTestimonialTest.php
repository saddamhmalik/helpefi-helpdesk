<?php

namespace Tests\Feature;

use App\Domains\Platform\Models\PlatformTestimonial;
use App\Domains\Tenancy\Models\CentralSetting;
use App\Models\PlatformUser;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminTestimonialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);
    }

    private function adminLogin(): void
    {
        $this->post('http://'.config('tenancy.central_app_domain').'/admin/login', [
            'email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'password' => PlatformUserSeeder::DEFAULT_PASSWORD,
        ]);
    }

    private function centralUrl(string $path = '/'): string
    {
        return 'http://'.config('tenancy.central_app_domain').$path;
    }

    public function test_platform_admin_can_manage_testimonials(): void
    {
        $this->adminLogin();

        $this->get($this->centralUrl('/admin/testimonials'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Admin/Testimonials/Index')
                ->where('testimonialsEnabled', true)
            );

        $this->post($this->centralUrl('/admin/testimonials'), [
            'quote' => 'Support finally feels unified for our team.',
            'name' => 'Alex P.',
            'role' => 'Support Manager',
            'company_type' => 'SaaS startup',
            'sort_order' => 0,
            'is_enabled' => true,
        ])->assertRedirect(route('central.admin.testimonials.index'));

        $this->assertDatabaseHas('platform_testimonials', [
            'name' => 'Alex P.',
            'company_type' => 'SaaS startup',
            'is_enabled' => true,
        ], 'central');

        $testimonial = PlatformTestimonial::query()->where('name', 'Alex P.')->firstOrFail();

        $this->get($this->centralUrl('/'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Home')
                ->where('testimonialsEnabled', true)
                ->where('testimonials.0.name', 'Alex P.')
            );

        $this->put($this->centralUrl("/admin/testimonials/{$testimonial->id}"), [
            'quote' => 'Updated quote for the homepage.',
            'name' => 'Alex P.',
            'role' => 'Support Manager',
            'company_type' => 'SaaS startup',
            'sort_order' => 1,
            'is_enabled' => true,
        ])->assertRedirect(route('central.admin.testimonials.index'));

        $this->assertDatabaseHas('platform_testimonials', [
            'id' => $testimonial->id,
            'quote' => 'Updated quote for the homepage.',
        ], 'central');
    }

    public function test_disabled_testimonials_are_hidden_from_homepage(): void
    {
        PlatformTestimonial::query()->create([
            'quote' => 'Hidden quote.',
            'name' => 'Hidden User',
            'role' => 'Ops Lead',
            'company_type' => 'Agency',
            'sort_order' => 99,
            'is_enabled' => false,
        ]);

        $this->get($this->centralUrl('/'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Home')
                ->where('testimonialsEnabled', true)
                ->where('testimonials', fn ($testimonials) => collect($testimonials)->doesntContain(
                    fn (array $item) => $item['name'] === 'Hidden User'
                ))
            );
    }

    public function test_master_toggle_hides_all_testimonials(): void
    {
        PlatformTestimonial::query()->create([
            'quote' => 'Visible quote.',
            'name' => 'Visible User',
            'role' => 'Ops Lead',
            'company_type' => 'Agency',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);

        $setting = CentralSetting::query()->firstOrFail();
        $setting->update(['testimonials_enabled' => false]);

        $this->get($this->centralUrl('/'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Home')
                ->where('testimonialsEnabled', false)
                ->where('testimonials', [])
            );
    }

    public function test_platform_admin_can_toggle_testimonial_visibility_setting(): void
    {
        $this->adminLogin();

        $this->put($this->centralUrl('/admin/testimonials/settings'), [
            'testimonials_enabled' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('central_settings', [
            'testimonials_enabled' => false,
        ], 'central');
    }

    public function test_platform_admin_can_delete_testimonial(): void
    {
        $this->adminLogin();

        $testimonial = PlatformTestimonial::query()->create([
            'quote' => 'Temporary quote.',
            'name' => 'Temp User',
            'role' => 'Director',
            'company_type' => 'Retail',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);

        $this->delete($this->centralUrl("/admin/testimonials/{$testimonial->id}"))
            ->assertRedirect(route('central.admin.testimonials.index'));

        $this->assertDatabaseMissing('platform_testimonials', [
            'id' => $testimonial->id,
        ], 'central');
    }

    public function test_view_only_permission_cannot_mutate_testimonials(): void
    {
        PlatformUser::query()->create([
            'name' => 'Viewer',
            'email' => 'viewer@helpdesk.test',
            'password' => 'password123',
            'is_active' => true,
        ])->syncRoles(['support']);

        $this->post($this->centralUrl('/admin/login'), [
            'email' => 'viewer@helpdesk.test',
            'password' => 'password123',
        ]);

        $this->get($this->centralUrl('/admin/testimonials'))->assertOk();
        $this->post($this->centralUrl('/admin/testimonials'), [
            'quote' => 'Denied',
            'name' => 'Denied',
            'role' => 'Role',
            'company_type' => 'Type',
        ])->assertForbidden();
    }

    public function test_testimonial_validation_rejects_missing_fields(): void
    {
        $this->adminLogin();

        $this->post($this->centralUrl('/admin/testimonials'), [
            'quote' => '',
            'name' => '',
            'role' => '',
            'company_type' => '',
        ])->assertSessionHasErrors(['quote', 'name', 'role', 'company_type']);
    }
}
