<?php

namespace Tests\Feature;

use App\Domains\Ai\Models\AiCopilotMessage;
use App\Domains\Ai\Models\AiSetting;
use App\Domains\Ai\Services\AgentCopilotService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgentCopilotTest extends TestCase
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

    public function test_agent_can_load_copilot_history(): void
    {
        $ticket = $this->seedTicketWithMessage();
        AiSetting::query()->create(['enabled' => true]);

        $this->getJson("/workspace/tickets/{$ticket->id}/ai/copilot")
            ->assertOk()
            ->assertJsonPath('messages', []);
    }

    public function test_agent_can_chat_with_local_fallback(): void
    {
        $ticket = $this->seedTicketWithMessage();
        AiSetting::query()->create(['enabled' => true]);

        $response = $this->postJson("/workspace/tickets/{$ticket->id}/ai/copilot", [
            'message' => 'Summarize this ticket.',
        ]);

        $response->assertOk()
            ->assertJsonPath('source', 'local')
            ->assertJsonStructure(['message' => ['role', 'content']]);

        $this->assertDatabaseHas('ai_copilot_messages', [
            'ticket_id' => $ticket->id,
            'role' => 'user',
        ]);
        $this->assertDatabaseHas('ai_copilot_messages', [
            'ticket_id' => $ticket->id,
            'role' => 'assistant',
        ]);
    }

    public function test_agent_can_clear_copilot_history(): void
    {
        $ticket = $this->seedTicketWithMessage();
        AiSetting::query()->create(['enabled' => true]);
        $agent = auth()->user();

        AiCopilotMessage::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'role' => 'user',
            'content' => 'Hello',
        ]);

        $this->deleteJson("/workspace/tickets/{$ticket->id}/ai/copilot")
            ->assertOk()
            ->assertJsonPath('cleared', true);

        $this->assertDatabaseMissing('ai_copilot_messages', [
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
        ]);
    }

    public function test_groq_mode_used_when_groq_api_key_configured(): void
    {
        config([
            'ai.provider' => 'groq',
            'ai.api_key' => 'test-groq-key',
            'ai.base_url' => 'https://api.groq.com/openai/v1',
            'ai.model' => 'llama-3.3-70b-versatile',
        ]);

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'The customer was double charged and needs billing review.']],
                ],
            ], 200),
        ]);

        $ticket = $this->seedTicketWithMessage();
        AiSetting::query()->create(['enabled' => true]);

        $response = $this->postJson("/workspace/tickets/{$ticket->id}/ai/copilot", [
            'message' => 'What happened here?',
        ]);

        $response->assertOk()
            ->assertJsonPath('source', 'groq')
            ->assertJsonPath('message.content', 'The customer was double charged and needs billing review.');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.groq.com/openai/v1/chat/completions');
    }

    public function test_copilot_history_returns_404_for_missing_ticket(): void
    {
        $this->seedTicketWithMessage();
        AiSetting::query()->create(['enabled' => true]);

        $this->getJson('/workspace/tickets/99999/ai/copilot')
            ->assertNotFound();
    }

    public function test_copilot_history_returns_most_recent_messages(): void
    {
        $ticket = $this->seedTicketWithMessage();
        AiSetting::query()->create(['enabled' => true]);
        $agent = auth()->user();

        for ($index = 1; $index <= 22; $index++) {
            AiCopilotMessage::query()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $agent->id,
                'role' => $index % 2 === 0 ? 'assistant' : 'user',
                'content' => "Message {$index}",
                'created_at' => now()->addSeconds($index),
            ]);
        }

        $response = $this->getJson("/workspace/tickets/{$ticket->id}/ai/copilot")
            ->assertOk();

        $messages = $response->json('messages');
        $this->assertCount(20, $messages);
        $this->assertSame('Message 3', $messages[0]['content']);
        $this->assertSame('Message 22', $messages[19]['content']);
    }

    public function test_customer_cannot_use_copilot(): void
    {
        $ticket = $this->seedTicketWithMessage();
        AiSetting::query()->create(['enabled' => true]);
        $customer = User::factory()->customer()->create();

        $this->expectException(AuthorizationException::class);

        app(AgentCopilotService::class)->chat($ticket->id, $customer->id, 'Summarize this ticket.');
    }

    public function test_provider_failure_does_not_persist_orphan_user_message(): void
    {
        config([
            'ai.provider' => 'openai',
            'ai.api_key' => 'test-key',
            'ai.base_url' => 'https://api.openai.com/v1',
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([], 500),
        ]);

        $ticket = $this->seedTicketWithMessage();
        AiSetting::query()->create(['enabled' => true]);

        $this->postJson("/workspace/tickets/{$ticket->id}/ai/copilot", [
            'message' => 'Summarize this ticket.',
        ])->assertStatus(500);

        $this->assertDatabaseMissing('ai_copilot_messages', [
            'ticket_id' => $ticket->id,
            'role' => 'user',
        ]);
    }
}
