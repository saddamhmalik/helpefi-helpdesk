<?php

namespace Tests\Feature;

use App\Domains\Ai\Models\AiSetting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiTest extends TestCase
{
    use RefreshDatabase;

    private function seedTicketWithMessage(): Ticket
    {
        $status = TicketStatus::query()->create(['name' => 'Open', 'slug' => 'open', 'color' => '#000', 'sort_order' => 1, 'is_closed' => false]);
        $priority = TicketPriority::query()->create(['name' => 'Normal', 'slug' => 'normal', 'sort_order' => 1]);
        $contact = Contact::query()->create(['name' => 'Jane Customer', 'email' => 'jane@example.com']);
        $agent = User::factory()->create();

        $ticket = Ticket::query()->create([
            'number' => 'HD-00100',
            'subject' => 'Billing question',
            'description' => 'Need help with invoice',
            'contact_id' => $contact->id,
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        TicketMessage::query()->create([
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'body' => 'I was charged twice on my last invoice.',
            'is_internal' => false,
        ]);

        $this->actingAs($agent);

        return $ticket;
    }

    public function test_admin_can_view_ai_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/settings/ai')
            ->assertOk();
    }

    public function test_agent_cannot_view_ai_settings(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get('/settings/ai')
            ->assertForbidden();
    }

    public function test_suggest_reply_returns_local_response(): void
    {
        $ticket = $this->seedTicketWithMessage();
        AiSetting::query()->create(['enabled' => true]);

        $response = $this->postJson("/workspace/tickets/{$ticket->id}/ai/suggest-reply");

        $response->assertOk()
            ->assertJsonPath('source', 'local')
            ->assertJsonStructure(['reply']);

        $this->assertStringContainsString('Jane Customer', $response->json('reply'));
    }

    public function test_ai_features_blocked_when_disabled(): void
    {
        $ticket = $this->seedTicketWithMessage();
        AiSetting::query()->create(['enabled' => false]);

        $this->postJson("/workspace/tickets/{$ticket->id}/ai/suggest-reply")
            ->assertForbidden();
    }

    public function test_summarize_returns_summary(): void
    {
        $ticket = $this->seedTicketWithMessage();
        AiSetting::query()->create(['enabled' => true]);

        $response = $this->postJson("/workspace/tickets/{$ticket->id}/ai/summarize");

        $response->assertOk()
            ->assertJsonPath('source', 'local')
            ->assertJsonStructure(['summary']);

        $this->assertStringContainsString('Billing question', $response->json('summary'));
    }

    public function test_kb_assist_returns_matching_articles(): void
    {
        $ticket = $this->seedTicketWithMessage();
        AiSetting::query()->create(['enabled' => true]);

        KnowledgeArticle::query()->create([
            'title' => 'Billing and invoices FAQ',
            'slug' => 'billing-invoices-faq',
            'excerpt' => 'How billing works',
            'body' => 'Details about invoices and duplicate charges.',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson("/workspace/tickets/{$ticket->id}/ai/kb-assist");

        $response->assertOk()
            ->assertJsonPath('source', 'local')
            ->assertJsonFragment(['title' => 'Billing and invoices FAQ']);
    }

    public function test_kb_assist_ignores_unpublished_articles(): void
    {
        $ticket = $this->seedTicketWithMessage();
        AiSetting::query()->create(['enabled' => true]);

        KnowledgeArticle::query()->create([
            'title' => 'Secret billing draft',
            'slug' => 'secret-billing-draft',
            'excerpt' => 'How billing works',
            'body' => 'Details about invoices and duplicate charges.',
            'is_published' => false,
        ]);

        KnowledgeArticle::query()->create([
            'title' => 'Billing and invoices FAQ',
            'slug' => 'billing-invoices-faq-published',
            'excerpt' => 'How billing works',
            'body' => 'Details about invoices and duplicate charges.',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson("/workspace/tickets/{$ticket->id}/ai/kb-assist");

        $response->assertOk()
            ->assertJsonMissing(['title' => 'Secret billing draft'])
            ->assertJsonFragment(['title' => 'Billing and invoices FAQ']);
    }

    public function test_openai_mode_used_when_api_key_configured(): void
    {
        config([
            'ai.provider' => 'openai',
            'ai.api_key' => 'test-key',
            'ai.base_url' => 'https://api.openai.com/v1',
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Thanks for your patience, we are reviewing your billing issue.']],
                ],
            ], 200),
        ]);

        $ticket = $this->seedTicketWithMessage();
        AiSetting::query()->create(['enabled' => true]);

        $response = $this->postJson("/workspace/tickets/{$ticket->id}/ai/suggest-reply");

        $response->assertOk()
            ->assertJsonPath('source', 'openai')
            ->assertJsonPath('reply', 'Thanks for your patience, we are reviewing your billing issue.');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/chat/completions');
    }

    public function test_groq_mode_used_when_groq_api_key_configured(): void
    {
        config([
            'ai.provider' => 'groq',
            'ai.api_key' => 'test-groq-key',
            'ai.base_url' => 'https://api.groq.com/openai/v1',
        ]);

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Thanks for your patience, we are reviewing your billing issue.']],
                ],
            ], 200),
        ]);

        $ticket = $this->seedTicketWithMessage();
        AiSetting::query()->create(['enabled' => true]);

        $response = $this->postJson("/workspace/tickets/{$ticket->id}/ai/suggest-reply");

        $response->assertOk()
            ->assertJsonPath('source', 'groq')
            ->assertJsonPath('reply', 'Thanks for your patience, we are reviewing your billing issue.');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.groq.com/openai/v1/chat/completions');
    }
}
