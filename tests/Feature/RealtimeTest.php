<?php

namespace Tests\Feature;

use App\Domains\Channels\Models\Channel;
use App\Domains\Chat\Models\ChatSession;
use App\Domains\Chat\Services\ChatAvailabilityService;
use App\Domains\Realtime\Services\RealtimePublisher;
use App\Domains\Realtime\Services\RealtimeTokenService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class, SlaSeeder::class]);
    }

    public function test_channel_token_verifies_for_matching_channel(): void
    {
        $service = app(RealtimeTokenService::class);
        $token = $service->forChannel('ticket.42');

        $this->assertTrue($service->verify($token, 'ticket.42'));
        $this->assertFalse($service->verify($token, 'ticket.99'));
    }

    public function test_agent_token_verifies_for_any_channel(): void
    {
        $agent = User::factory()->create();
        $service = app(RealtimeTokenService::class);
        $token = $service->agentToken($agent);

        $this->assertTrue($service->verify($token, 'workspace'));
        $this->assertTrue($service->verify($token, 'ticket.1'));
    }

    public function test_ticket_reply_publishes_realtime_message(): void
    {
        $this->mock(ChatAvailabilityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        $channel = Channel::query()->where('slug', 'chat')->firstOrFail();
        $headers = [
            'X-Widget-Key' => $channel->settings['widget_key'],
            'Accept' => 'application/json',
        ];

        $start = $this->postJson('/api/v1/chat/sessions', [
            'name' => 'Alex',
            'email' => 'alex@example.com',
            'message' => 'Hello',
        ], $headers)->json();

        $session = ChatSession::query()->where('uuid', $start['session_uuid'])->firstOrFail();
        $agent = User::factory()->create();

        Redis::spy();

        app(\App\Domains\Tickets\Services\TicketService::class)->reply(
            $session->ticket_id,
            $agent->id,
            'On my way!',
        );

        Redis::shouldHaveReceived('publish')
            ->atLeast()
            ->once()
            ->withArgs(function (string $channel, string $payload) use ($session) {
                $data = json_decode($payload, true);

                return str_contains($channel, 'ticket.'.$session->ticket_id)
                    && ($data['event'] ?? null) === 'message.created'
                    && ($data['data']['message']['body'] ?? null) === 'On my way!';
            });
    }

    public function test_chat_session_includes_realtime_credentials(): void
    {
        $this->mock(ChatAvailabilityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        $channel = Channel::query()->where('slug', 'chat')->firstOrFail();

        $this->postJson('/api/v1/chat/sessions', [
            'name' => 'Alex',
            'email' => 'alex@example.com',
            'message' => 'Need help',
        ], [
            'X-Widget-Key' => $channel->settings['widget_key'],
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'realtime' => ['url', 'channel', 'token'],
            ]);
    }

    public function test_realtime_publisher_emits_queue_update(): void
    {
        $open = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();
        $ticket = Ticket::query()->create([
            'number' => 'HD-RT01',
            'subject' => 'Realtime ticket',
            'ticket_status_id' => $open->id,
            'ticket_priority_id' => $priority->id,
        ]);

        Redis::spy();

        app(RealtimePublisher::class)->ticketUpdated($ticket->id, [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
        ]);

        Redis::shouldHaveReceived('publish')
            ->atLeast()
            ->once()
            ->withArgs(function (string $channel, string $payload) {
                $data = json_decode($payload, true);

                return str_contains($channel, 'workspace')
                    && ($data['event'] ?? null) === 'queue.updated';
            });
    }
}
