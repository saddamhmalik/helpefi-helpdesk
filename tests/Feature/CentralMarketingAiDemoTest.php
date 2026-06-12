<?php

namespace Tests\Feature;

use App\Domains\Ai\Services\CentralMarketingAiService;
use Tests\TestCase;

class CentralMarketingAiDemoTest extends TestCase
{
    public function test_marketing_ai_demo_service_and_endpoint(): void
    {
        config([
            'ai.api_key' => null,
            'ai.marketing_demo_enabled' => true,
            'telescope.enabled' => false,
        ]);

        $service = app(CentralMarketingAiService::class);
        $result = $service->ask('How does AI deflection work?');

        $this->assertSame('local', $result['source']);
        $this->assertNotEmpty($result['answer']);
        $this->assertNotEmpty($result['articles']);

        $this->postJson(
            'http://'.config('tenancy.central_app_domain').'/api/marketing/ai-demo',
            ['query' => 'What is Agent Copilot?'],
        )
            ->assertOk()
            ->assertJsonPath('source', 'local');

        $this->postJson(
            'http://'.config('tenancy.central_app_domain').'/api/marketing/ai-demo',
            ['query' => ''],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['query']);

        config(['ai.marketing_demo_enabled' => false]);

        $this->postJson(
            'http://'.config('tenancy.central_app_domain').'/api/marketing/ai-demo',
            ['query' => 'What is Agent Copilot?'],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['query']);
    }
}
