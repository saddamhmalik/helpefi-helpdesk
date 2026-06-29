<?php

namespace Tests\Feature;

use App\Domains\Platform\Models\MarketingBlogPost;
use App\Domains\Platform\Models\MarketingContentDraft;
use App\Domains\Platform\Models\MarketingPageContent;
use App\Domains\Platform\Services\MarketingContentGenerationService;
use App\Domains\Platform\Services\MarketingContentPublishService;
use Database\Seeders\MarketingBlogPostSeeder;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingContentGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            MarketingBlogPostSeeder::class,
            PlatformPermissionSeeder::class,
            PlatformUserSeeder::class,
        ]);
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

    public function test_admin_can_view_content_generation_index(): void
    {
        $this->adminLogin();

        $this->get($this->centralUrl('/admin/content'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Admin/Content/Index')
                ->has('drafts')
            );
    }

    public function test_local_generation_creates_editable_draft(): void
    {
        config(['ai.api_key' => null]);

        $this->adminLogin();

        $this->post($this->centralUrl('/admin/content'), [
            'content_type' => 'feature',
            'title' => 'Workflow automation for support teams',
            'brief' => 'Explain how automation rules reduce manual triage for SaaS support teams.',
            'slug' => 'workflow-automation-test',
        ])->assertRedirect();

        $draft = MarketingContentDraft::query()->first();
        $this->assertNotNull($draft);
        $this->assertSame('feature', $draft->content_type);
        $this->assertSame('draft', $draft->status);
        $this->assertNotEmpty($draft->generated_content);
        $this->assertNotEmpty($draft->seo);
        $this->assertNotEmpty($draft->schema_markup);
        $this->assertNotEmpty($draft->internal_links);
    }

    public function test_admin_can_edit_draft_before_publishing(): void
    {
        config(['ai.api_key' => null]);

        $draft = app(MarketingContentGenerationService::class)->generate([
            'content_type' => 'vertical',
            'title' => 'Helpdesk for healthcare clinics',
            'brief' => 'HIPAA-aware workflows, appointment routing, and patient portal support.',
            'slug' => 'healthcare-clinics-test',
            'industry' => 'Healthcare',
        ]);

        $this->adminLogin();

        $this->put($this->centralUrl('/admin/content/'.$draft->id), [
            'title' => 'Helpdesk software for healthcare clinics',
            'slug' => 'healthcare-clinics-test',
            'brief' => $draft->brief,
            'edited_content' => json_encode([
                'nav_label' => 'Healthcare',
                'badge' => 'Helpdesk for clinics',
                'hero_title' => 'Helpdesk software for healthcare clinics',
                'hero_highlight' => 'Secure patient support workflows',
                'hero_subtitle' => 'Route intake, billing, and clinical admin questions with SLA tracking.',
                'pains' => [
                    ['title' => 'Mixed channels', 'body' => 'Phone, portal, and email requests lack a shared queue.'],
                ],
                'features' => [
                    ['title' => 'Role-based routing', 'body' => 'Send tickets to billing, front desk, or clinical ops.'],
                ],
                'faq' => [
                    ['q' => 'Can we restrict PHI in tickets?', 'a' => 'Use private fields and role permissions to control sensitive data.'],
                ],
                'cta_title' => 'Support patients with confidence',
                'cta_body' => 'Start a free trial and connect your clinic inbox.',
            ]),
            'seo' => json_encode([
                'seo_title' => 'Healthcare Helpdesk Software · Helpefi',
                'meta_description' => 'Route clinic support with SLA policies, secure workflows, and a branded patient portal.',
                'keywords' => 'healthcare helpdesk, clinic support, patient portal',
            ]),
            'schema_markup' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => 'Healthcare helpdesk',
            ]),
            'internal_links' => json_encode([
                ['type' => 'vertical', 'slug' => 'ecommerce', 'label' => 'Ecommerce', 'anchor_text' => 'Ecommerce support'],
            ]),
        ])->assertRedirect();

        $draft->refresh();
        $this->assertSame('ready', $draft->status);
        $this->assertSame('Helpdesk software for healthcare clinics', $draft->edited_content['hero_title'] ?? null);
    }

    public function test_publish_feature_draft_overrides_live_page_content(): void
    {
        config(['ai.api_key' => null]);

        $draft = app(MarketingContentGenerationService::class)->generate([
            'content_type' => 'feature',
            'title' => 'Unique shared inbox positioning',
            'brief' => 'Focus on collision detection and multi-brand routing for agencies.',
            'slug' => 'shared-inbox',
        ]);

        app(MarketingContentPublishService::class)->publish($draft->id);

        $draft->refresh();
        $this->assertSame(MarketingContentDraft::STATUS_PUBLISHED, $draft->status);

        $override = MarketingPageContent::query()
            ->where('page_type', 'feature')
            ->where('slug', 'shared-inbox')
            ->first();

        $this->assertNotNull($override);
        $this->assertSame('Unique shared inbox positioning', $override->content['hero_title'] ?? null);

        $this->get($this->centralUrl('/shared-inbox'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/FeatureLanding')
                ->where('content.hero_title', 'Unique shared inbox positioning')
            );
    }

    public function test_publish_blog_outline_creates_draft_blog_post(): void
    {
        config(['ai.api_key' => null]);

        $draft = app(MarketingContentGenerationService::class)->generate([
            'content_type' => 'blog_outline',
            'title' => 'How to evaluate ITSM add-ons',
            'brief' => 'Guide for buyers comparing service desk and ITSM modules.',
            'slug' => 'evaluate-itsm-addons-guide',
        ]);

        app(MarketingContentPublishService::class)->publish($draft->id);

        $post = MarketingBlogPost::query()->where('slug', 'evaluate-itsm-addons-guide')->first();
        $this->assertNotNull($post);
        $this->assertSame(MarketingBlogPost::STATUS_DRAFT, $post->status);
        $this->assertStringContainsString('Introduction', $post->body);
    }

    public function test_save_blocks_near_duplicate_edited_content(): void
    {
        config(['ai.api_key' => null]);

        $draft = app(MarketingContentGenerationService::class)->generate([
            'content_type' => 'feature',
            'title' => 'Unique automation page',
            'brief' => 'Automation rules for SaaS onboarding teams with unique positioning.',
            'slug' => 'automation-unique-test',
        ]);

        $existing = config('marketing_feature_content.shared-inbox');
        $this->assertIsArray($existing);

        $this->adminLogin();

        $this->put($this->centralUrl('/admin/content/'.$draft->id), [
            'title' => $draft->title,
            'slug' => $draft->slug,
            'brief' => $draft->brief,
            'edited_content' => json_encode($existing),
            'seo' => json_encode($draft->seo ?? []),
            'schema_markup' => json_encode($draft->schema_markup ?? []),
            'internal_links' => json_encode($draft->internal_links ?? []),
        ])->assertSessionHasErrors('edited_content');
    }
}
