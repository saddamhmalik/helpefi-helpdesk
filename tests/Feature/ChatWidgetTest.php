<?php

namespace Tests\Feature;

use App\Domains\Channels\Models\Channel;
use App\Domains\Chat\Models\ChatSession;
use App\Domains\Chat\Services\ChatAvailabilityService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class ChatWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function chatChannel(): Channel
    {
        return Channel::query()->where('slug', 'chat')->firstOrFail();
    }

    private function widgetHeaders(?string $key = null): array
    {
        return [
            'X-Widget-Key' => $key ?? $this->chatChannel()->settings['widget_key'],
            'Accept' => 'application/json',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class, SlaSeeder::class]);
    }

    public function test_widget_config_returns_online_state(): void
    {
        $this->mock(ChatAvailabilityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        $this->getJson('/api/v1/chat/config', $this->widgetHeaders())
            ->assertOk()
            ->assertJsonPath('online', true)
            ->assertJsonStructure(['greeting', 'offline_message']);
    }

    public function test_visitor_can_start_live_chat_session(): void
    {
        $this->mock(ChatAvailabilityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        $response = $this->postJson('/api/v1/chat/sessions', [
            'name' => 'Alex',
            'email' => 'alex@example.com',
            'message' => 'Need help with billing',
            'page_url' => 'https://example.com/pricing',
        ], $this->widgetHeaders())
            ->assertOk()
            ->assertJsonPath('mode', 'live');

        $this->assertDatabaseHas('tickets', [
            'subject' => 'Live chat from Alex',
        ]);

        $this->assertDatabaseHas('chat_sessions', [
            'uuid' => $response->json('session_uuid'),
        ]);

        $this->assertNotEmpty($response->json('session_token'));
        $this->assertNotEmpty($response->json('messages'));
    }

    public function test_visitor_can_send_message_and_poll_agent_reply(): void
    {
        $this->mock(ChatAvailabilityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        $start = $this->postJson('/api/v1/chat/sessions', [
            'name' => 'Alex',
            'email' => 'alex@example.com',
            'message' => 'Hello',
        ], $this->widgetHeaders())->json();

        $headers = array_merge($this->widgetHeaders(), [
            'X-Session-Token' => $start['session_token'],
        ]);

        $this->postJson('/api/v1/chat/sessions/'.$start['session_uuid'].'/messages', [
            'body' => 'Follow-up question',
        ], $headers)->assertOk();

        $session = ChatSession::query()->where('uuid', $start['session_uuid'])->firstOrFail();
        $agent = User::factory()->create();

        app(\App\Domains\Tickets\Services\TicketService::class)->reply(
            $session->ticket_id,
            $agent->id,
            'Happy to help!',
        );

        $poll = $this->getJson('/api/v1/chat/sessions/'.$start['session_uuid'].'/poll', $headers)
            ->assertOk();

        $this->assertTrue(collect($poll->json('messages'))->contains(fn ($message) => $message['body'] === 'Happy to help!'));
    }

    public function test_agent_html_reply_is_plain_text_in_widget_poll(): void
    {
        $this->mock(ChatAvailabilityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        $start = $this->postJson('/api/v1/chat/sessions', [
            'name' => 'Alex',
            'email' => 'alex@example.com',
            'message' => 'Hello',
        ], $this->widgetHeaders())->json();

        $headers = array_merge($this->widgetHeaders(), [
            'X-Session-Token' => $start['session_token'],
        ]);

        $session = ChatSession::query()->where('uuid', $start['session_uuid'])->firstOrFail();
        $agent = User::factory()->create();

        app(\App\Domains\Tickets\Services\TicketService::class)->reply(
            $session->ticket_id,
            $agent->id,
            '<p>how are you</p><p><br></p>',
        );

        $poll = $this->getJson('/api/v1/chat/sessions/'.$start['session_uuid'].'/poll', $headers)
            ->assertOk();

        $agentMessage = collect($poll->json('messages'))->firstWhere('author_type', 'agent');

        $this->assertSame('how are you', $agentMessage['body']);
        $this->assertStringNotContainsString('<p>', $agentMessage['body']);
    }

    public function test_agent_reply_on_chat_ticket_does_not_send_email(): void
    {
        Mail::fake();
        $this->mock(ChatAvailabilityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        $start = $this->postJson('/api/v1/chat/sessions', [
            'name' => 'Alex',
            'email' => 'alex@example.com',
            'message' => 'Hello',
        ], $this->widgetHeaders())->json();

        $session = ChatSession::query()->where('uuid', $start['session_uuid'])->firstOrFail();
        $agent = User::factory()->create();

        app(\App\Domains\Tickets\Services\TicketService::class)->reply(
            $session->ticket_id,
            $agent->id,
            'On it',
        );

        Mail::assertNothingSent();
    }

    public function test_offline_mode_creates_email_ticket(): void
    {
        $this->mock(ChatAvailabilityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(false);
        });

        $this->postJson('/api/v1/chat/sessions', [
            'name' => 'Sam',
            'email' => 'sam@example.com',
            'message' => 'Please call me back',
        ], $this->widgetHeaders())
            ->assertCreated()
            ->assertJsonPath('mode', 'offline')
            ->assertJsonStructure(['ticket_number', 'message']);

        $this->assertDatabaseHas('tickets', [
            'subject' => 'Offline chat from Sam',
        ]);

        $this->assertDatabaseMissing('chat_sessions', [
            'visitor_name' => 'Sam',
        ]);
    }

    public function test_invalid_widget_key_is_rejected(): void
    {
        $this->getJson('/api/v1/chat/config', [
            'X-Widget-Key' => 'invalid-key',
            'Accept' => 'application/json',
        ])->assertForbidden();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
