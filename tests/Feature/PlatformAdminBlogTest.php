<?php

namespace Tests\Feature;

use App\Domains\Platform\Models\MarketingBlogPost;
use Database\Seeders\MarketingBlogPostSeeder;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminBlogTest extends TestCase
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

    public function test_platform_admin_can_manage_blog_posts(): void
    {
        $this->adminLogin();

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/blog')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Central/Admin/Blog/Index'));

        $this->post('http://'.config('tenancy.central_app_domain').'/admin/blog', [
            'title' => 'Ticket management best practices',
            'slug' => 'ticket-management-best-practices',
            'excerpt' => 'How to structure queues, SLAs, and routing before volume becomes chaos.',
            'body' => "First paragraph about ticket queues.\n\nSecond paragraph about SLA policies.",
            'status' => MarketingBlogPost::STATUS_PUBLISHED,
            'seo_title' => 'Ticket Management Best Practices',
            'seo_description' => 'Practical ticket management tips for growing support teams.',
        ])->assertRedirect(route('central.admin.blog.index'));

        $this->assertDatabaseHas('marketing_blog_posts', [
            'slug' => 'ticket-management-best-practices',
            'status' => MarketingBlogPost::STATUS_PUBLISHED,
        ], 'central');

        $this->get('http://'.config('tenancy.central_app_domain').'/blog/ticket-management-best-practices')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Blog/Show')
                ->where('post.slug', 'ticket-management-best-practices')
                ->where('post.title', 'Ticket management best practices')
            );
    }

    public function test_draft_blog_posts_are_not_public(): void
    {
        MarketingBlogPost::query()->create([
            'slug' => 'draft-only-post',
            'title' => 'Draft only',
            'excerpt' => 'Should stay hidden.',
            'body' => 'Draft body.',
            'status' => MarketingBlogPost::STATUS_DRAFT,
        ]);

        $this->get('http://'.config('tenancy.central_app_domain').'/blog/draft-only-post')
            ->assertNotFound();
    }

    public function test_seeded_sample_post_is_public(): void
    {
        $this->seed(MarketingBlogPostSeeder::class);

        $this->get('http://'.config('tenancy.central_app_domain').'/blog/ai-helpdesk-software-guide')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Blog/Show')
                ->where('post.slug', 'ai-helpdesk-software-guide')
            );
    }
}
