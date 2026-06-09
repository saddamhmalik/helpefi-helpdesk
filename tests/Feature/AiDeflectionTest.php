<?php

namespace Tests\Feature;

use App\Domains\Ai\Models\AiDeflectionEvent;
use App\Domains\Ai\Models\AiSetting;
use App\Domains\Channels\Models\Channel;
use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiDeflectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class]);
    }

    private function enableDeflection(): void
    {
        AiSetting::query()->create([
            'enabled' => true,
            'deflection_enabled' => true,
            'deflection_portal_enabled' => true,
            'deflection_widget_enabled' => true,
        ]);
    }

    public function test_portal_deflection_returns_local_answer_with_articles(): void
    {
        $this->enableDeflection();

        KnowledgeArticle::query()->create([
            'title' => 'Reset your password',
            'slug' => 'reset-password',
            'excerpt' => 'Use the forgot password link on the login page.',
            'body' => 'Detailed password reset instructions.',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/deflection/ask', [
            'query' => 'How do I reset my password?',
            'channel' => 'portal',
        ])->assertOk();

        $this->assertNotEmpty($response->json('session_id'));
        $this->assertStringContainsString('Reset your password', $response->json('answer'));
        $this->assertDatabaseHas('ai_deflection_events', [
            'event_type' => AiDeflectionEvent::EVENT_QUERY,
            'channel' => AiDeflectionEvent::CHANNEL_PORTAL,
        ]);
    }

    public function test_deflection_disabled_returns_forbidden(): void
    {
        AiSetting::query()->create([
            'enabled' => true,
            'deflection_enabled' => false,
        ]);

        $this->postJson('/api/v1/deflection/ask', [
            'query' => 'Help',
            'channel' => 'portal',
        ])->assertForbidden();
    }

    public function test_feedback_records_helpful_event(): void
    {
        $this->enableDeflection();

        $ask = $this->postJson('/api/v1/deflection/ask', [
            'query' => 'billing help',
            'channel' => 'portal',
        ])->json();

        $this->postJson('/api/v1/deflection/feedback', [
            'session_id' => $ask['session_id'],
            'channel' => 'portal',
            'helpful' => true,
        ])->assertOk();

        $this->assertDatabaseHas('ai_deflection_events', [
            'session_id' => $ask['session_id'],
            'event_type' => AiDeflectionEvent::EVENT_HELPFUL,
        ]);
    }

    public function test_escalation_creates_portal_ticket(): void
    {
        $this->enableDeflection();

        $ask = $this->postJson('/api/v1/deflection/ask', [
            'query' => 'Need human help',
            'channel' => 'portal',
        ])->json();

        $this->postJson('/api/v1/deflection/escalate', [
            'session_id' => $ask['session_id'],
            'channel' => 'portal',
            'name' => 'Sam',
            'email' => 'sam@example.com',
            'subject' => 'Still need help',
            'description' => 'The bot could not answer my question.',
        ])
            ->assertCreated()
            ->assertJsonStructure(['ticket_number', 'message']);

        $this->assertDatabaseHas('tickets', [
            'subject' => 'Still need help',
        ]);

        $this->assertDatabaseHas('ai_deflection_events', [
            'session_id' => $ask['session_id'],
            'event_type' => AiDeflectionEvent::EVENT_TICKET_CREATED,
        ]);
    }

    public function test_widget_deflection_requires_valid_widget_key(): void
    {
        $this->enableDeflection();

        $this->postJson('/api/v1/deflection/ask', [
            'query' => 'Help',
            'channel' => 'widget',
        ], [
            'X-Widget-Key' => 'invalid',
            'Accept' => 'application/json',
        ])->assertForbidden();
    }

    public function test_dashboard_includes_deflection_metrics(): void
    {
        $this->enableDeflection();

        $this->postJson('/api/v1/deflection/ask', [
            'query' => 'billing',
            'channel' => 'portal',
        ]);

        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Index')
                ->where('deflection.queries', 1));
    }

    public function test_chat_config_includes_deflection_flag(): void
    {
        $this->enableDeflection();
        $channel = Channel::query()->where('slug', 'chat')->firstOrFail();

        $this->getJson('/api/v1/chat/config', [
            'X-Widget-Key' => $channel->settings['widget_key'],
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('deflection_enabled', true);
    }
}
