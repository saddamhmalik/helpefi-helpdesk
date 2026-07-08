<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PillarPagesTest extends TestCase
{
    use RefreshDatabase;

    private const PILLAR_SLUGS = ['ai-agent', 'shared-inbox', 'sla-management'];

    private function centralUrl(string $path = '/'): string
    {
        return 'http://'.config('tenancy.central_app_domain').$path;
    }

    public function test_pillar_feature_pages_render_with_long_form_content(): void
    {
        foreach (self::PILLAR_SLUGS as $slug) {
            $this->get($this->centralUrl('/'.$slug))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Central/FeatureLanding')
                    ->where('feature', $slug)
                    ->has('content.intro')
                    ->has('content.deep_dives')
                    ->has('content.use_cases.items')
                    ->has('content.faq')
                    ->has('content.related_links')
                    ->has('content.conclusion.body')
                );
        }
    }

    public function test_pricing_page_renders_long_form_content(): void
    {
        $this->get($this->centralUrl('/pricing'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/MarketingStaticPage')
                ->where('page', 'pricing')
                ->has('content.intro')
                ->has('content.deep_dives')
                ->has('content.faq')
                ->has('content.related_links')
            );
    }

    public function test_pillar_pages_meet_minimum_word_count_in_config(): void
    {
        foreach (self::PILLAR_SLUGS as $slug) {
            $content = config('marketing_feature_content.'.$slug);
            $this->assertIsArray($content);
            $this->assertGreaterThanOrEqual(2500, $this->longFormWordCount($content), $slug);
        }

        $pricing = config('marketing_static_content.pricing');
        $this->assertIsArray($pricing);
        $this->assertGreaterThanOrEqual(2500, $this->longFormWordCount($pricing), 'pricing');
    }

    public function test_pillar_first_paint_html_includes_long_form_sections(): void
    {
        foreach ([
            '/',
            '/ai-agent',
            '/shared-inbox',
            '/sla-management',
            '/pricing',
        ] as $path) {
            $response = $this->get($this->centralUrl($path));
            $response->assertOk();
            $html = $response->getContent();
            $this->assertStringContainsString('marketing-first-paint', $html, $path);
            $this->assertGreaterThanOrEqual(2500, str_word_count(strip_tags($html)), $path.' HTML word count');
        }
    }

    private function longFormWordCount(array $content): int
    {
        $parts = [];

        foreach (['intro', 'hero_subtitle'] as $key) {
            if (! empty($content[$key]) && is_string($content[$key])) {
                $parts[] = $content[$key];
            }
        }

        if (! empty($content['conclusion']['body'])) {
            $parts[] = $content['conclusion']['body'];
        }

        foreach (['features', 'deep_dives', 'sections'] as $key) {
            foreach ($content[$key] ?? [] as $item) {
                if (is_array($item)) {
                    $parts[] = ($item['title'] ?? '').' '.($item['body'] ?? '');
                }
            }
        }

        foreach ($content['use_cases']['items'] ?? [] as $item) {
            $parts[] = ($item['title'] ?? '').' '.($item['body'] ?? '');
        }

        foreach ($content['faq'] ?? [] as $item) {
            $parts[] = ($item['q'] ?? '').' '.($item['a'] ?? '');
        }

        return str_word_count(preg_replace('/\s+/', ' ', strip_tags(implode(' ', $parts))) ?? '');
    }
}
