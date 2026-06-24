<?php

namespace Tests\Feature;

use App\Domains\Channels\Models\Channel;
use App\Domains\Chat\Models\ChatSession;
use App\Domains\Chat\Services\ChatAvailabilityService;
use App\Domains\Realtime\Services\RealtimePublisher;
use App\Domains\Realtime\Services\RealtimeTokenService;
use App\Domains\Realtime\Support\RealtimeChannelNames;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Tickets\Services\TicketService;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TenantTestCase;

class RealtimeTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class, SlaSeeder::class]);
    }

    private function mockRealtimeRedis(): \Mockery\MockInterface
    {
        $connection = \Mockery::mock(\Illuminate\Redis\Connections\Connection::class);

        Redis::partialMock()
            ->shouldReceive('connection')
            ->with('realtime')
            ->andReturn($connection);

        return $connection;
    }

    public function test_channel_token_verifies_for_matching_channel(): void
    {
        $service = app(RealtimeTokenService::class);
        $channel = RealtimeChannelNames::ticket(42);
        $token = $service->forChannel($channel);

        $this->assertTrue($service->verify($token, $channel));
        $this->assertFalse($service->verify($token, RealtimeChannelNames::ticket(99)));
    }

    public function test_agent_token_verifies_for_workspace_and_own_user_channel(): void
    {
        $agent = User::factory()->create();
        $service = app(RealtimeTokenService::class);
        $token = $service->agentToken($agent);

        $this->assertTrue($service->verify($token, RealtimeChannelNames::workspace()));
        $this->assertTrue($service->verify($token, RealtimeChannelNames::user($agent->id)));
        $this->assertFalse($service->verify($token, RealtimeChannelNames::ticket(1)));
        $this->assertFalse($service->verify($token, RealtimeChannelNames::user($agent->id + 1)));
    }

    public function test_ticket_channel_token_verifies_only_for_matching_ticket(): void
    {
        $agent = User::factory()->create();
        $service = app(RealtimeTokenService::class);
        $channel = RealtimeChannelNames::ticket(42);
        $token = $service->forChannel($channel, $agent->id);

        $this->assertTrue($service->verify($token, $channel));
        $this->assertFalse($service->verify($token, RealtimeChannelNames::ticket(99)));
        $this->assertFalse($service->verify($token, RealtimeChannelNames::workspace()));
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

        $start = $this->tenantPostJson('/api/v1/chat/sessions', [
            'name' => 'Alex',
            'email' => 'alex@example.com',
            'message' => 'Hello',
        ], $headers)->json();

        $session = ChatSession::query()->where('uuid', $start['session_uuid'])->firstOrFail();
        $agent = User::factory()->create();

        $realtimeRedis = $this->mockRealtimeRedis();
        $ticketPublished = false;
        $realtimeRedis->shouldReceive('publish')->zeroOrMoreTimes()->andReturnUsing(function (string $channel, string $payload) use (&$ticketPublished, $session) {
            $laravelPrefix = config('database.redis.options.prefix');
            $this->assertFalse(str_starts_with($channel, (string) $laravelPrefix));
            $this->assertStringStartsWith(config('realtime.redis_prefix'), $channel);

            if (! str_contains($channel, 'ticket.'.$session->ticket_id)) {
                return 1;
            }

            $data = json_decode($payload, true);
            $ticketPublished = ($data['event'] ?? null) === 'message.created'
                && ($data['data']['message']['body'] ?? null) === 'On my way!';

            return 1;
        });

        app(\App\Domains\Tickets\Services\TicketService::class)->reply(
            $session->ticket_id,
            $agent->id,
            'On my way!',
        );

        $this->assertTrue($ticketPublished);
    }

    public function test_chat_session_includes_realtime_credentials(): void
    {
        $this->mock(ChatAvailabilityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        $channel = Channel::query()->where('slug', 'chat')->firstOrFail();

        $this->tenantPostJson('/api/v1/chat/sessions', [
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

        $realtimeRedis = $this->mockRealtimeRedis();
        $queuePublished = false;
        $realtimeRedis->shouldReceive('publish')->zeroOrMoreTimes()->andReturnUsing(function (string $channel, string $payload) use (&$queuePublished) {
            $laravelPrefix = config('database.redis.options.prefix');
            $this->assertFalse(str_starts_with($channel, (string) $laravelPrefix));

            if (! str_contains($channel, 'workspace')) {
                return 1;
            }

            $data = json_decode($payload, true);
            $queuePublished = ($data['event'] ?? null) === 'queue.updated';

            return 1;
        });

        app(RealtimePublisher::class)->ticketUpdated($ticket->id, [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
        ]);

        $this->assertTrue($queuePublished);
    }

    public function test_ticket_creation_publishes_queue_update(): void
    {
        $user = User::factory()->create();
        $status = TicketStatus::query()->where('slug', 'open')->firstOrFail();
        $priority = TicketPriority::query()->where('slug', 'normal')->firstOrFail();
        $contact = Contact::query()->create([
            'name' => 'Queue Test',
            'email' => 'queue-test@example.com',
        ]);

        $realtimeRedis = $this->mockRealtimeRedis();
        $queuePublished = false;
        $realtimeRedis->shouldReceive('publish')->zeroOrMoreTimes()->andReturnUsing(function (string $channel, string $payload) use (&$queuePublished) {
            if (! str_contains($channel, 'workspace')) {
                return 1;
            }

            $data = json_decode($payload, true);
            $queuePublished = ($data['event'] ?? null) === 'queue.updated'
                && ($data['data']['ticket']['subject'] ?? null) === 'Realtime queue ticket';

            return 1;
        });

        app(TicketService::class)->create([
            'subject' => 'Realtime queue ticket',
            'contact_id' => $contact->id,
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ], $user->id);

        $this->assertTrue($queuePublished);
    }
}
