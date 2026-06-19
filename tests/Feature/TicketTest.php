<?php

namespace Tests\Feature;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    private function seedTicketMeta(): array
    {
        $status = TicketStatus::query()->create(['name' => 'Open', 'slug' => 'open', 'color' => '#000', 'sort_order' => 1, 'is_closed' => false]);
        $priority = TicketPriority::query()->create(['name' => 'Normal', 'slug' => 'normal', 'sort_order' => 1]);

        return [$status, $priority];
    }

    public function test_authenticated_user_can_view_tickets(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/tickets')
            ->assertOk();
    }

    public function test_create_ticket_adds_description_as_contact_message(): void
    {
        [$status, $priority] = $this->seedTicketMeta();
        $user = User::factory()->create();
        $contact = Contact::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

        $this->actingAs($user)
            ->post('/tickets', [
                'subject' => 'Broken login',
                'description' => '<p>Cannot sign in since yesterday.</p>',
                'contact_id' => $contact->id,
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
            ])
            ->assertRedirect();

        $ticket = Ticket::query()->where('subject', 'Broken login')->firstOrFail();

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'body' => '<p>Cannot sign in since yesterday.</p>',
            'is_internal' => false,
        ]);
        $this->assertSame(1, $ticket->messages()->count());
    }

    public function test_create_ticket_requires_requester(): void
    {
        [$status, $priority] = $this->seedTicketMeta();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/tickets', [
                'subject' => 'Internal follow-up',
                'description' => '<p>Callback requested by phone.</p>',
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
            ])
            ->assertSessionHasErrors(['requester_email']);

        $this->assertDatabaseMissing('tickets', [
            'subject' => 'Internal follow-up',
        ]);
    }

    public function test_create_ticket_accepts_new_requester_email(): void
    {
        [$status, $priority] = $this->seedTicketMeta();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/tickets', [
                'subject' => 'Phone callback',
                'description' => '<p>Callback requested by phone.</p>',
                'requester_email' => 'caller@example.com',
                'requester_name' => 'Caller',
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'subject' => 'Phone callback',
        ]);

        $this->assertDatabaseHas('contacts', [
            'email' => 'caller@example.com',
        ]);
    }

    public function test_user_can_watch_and_unwatch_ticket(): void
    {
        [$status, $priority] = $this->seedTicketMeta();
        $user = User::factory()->create();
        $ticket = Ticket::query()->create([
            'number' => 'HD-00001',
            'subject' => 'Test',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $this->actingAs($user)
            ->post("/tickets/{$ticket->id}/watchers")
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_watchers', [
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete("/tickets/{$ticket->id}/watchers/{$user->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('ticket_watchers', [
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_save_ticket_view(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/ticket-views', [
                'name' => 'My view',
                'visibility' => 'private',
                'filters' => ['search' => 'billing'],
                'is_default' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_views', [
            'user_id' => $user->id,
            'name' => 'My view',
            'is_default' => 1,
        ]);
    }

    public function test_user_can_merge_tickets(): void
    {
        [$status, $priority] = $this->seedTicketMeta();
        $user = User::factory()->create();

        $target = Ticket::query()->create([
            'number' => 'HD-00001',
            'subject' => 'Target',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $source = Ticket::query()->create([
            'number' => 'HD-00002',
            'subject' => 'Source',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        TicketMessage::query()->create([
            'ticket_id' => $source->id,
            'user_id' => $user->id,
            'body' => 'Source message',
            'is_internal' => false,
        ]);

        $this->actingAs($user)
            ->post("/tickets/{$target->id}/merge", ['source_ticket_id' => $source->id])
            ->assertRedirect("/tickets/{$target->id}");

        $source->refresh();
        $this->assertSame($target->id, $source->merged_into_ticket_id);
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $target->id,
            'body' => 'Source message',
            'merged_from_ticket_id' => $source->id,
        ]);
    }

    public function test_merge_without_importing_conversation_keeps_messages_on_source(): void
    {
        [$status, $priority] = $this->seedTicketMeta();
        $user = User::factory()->create();

        $target = Ticket::query()->create([
            'number' => 'HD-00001',
            'subject' => 'Target',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $source = Ticket::query()->create([
            'number' => 'HD-00002',
            'subject' => 'Source',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        TicketMessage::query()->create([
            'ticket_id' => $source->id,
            'user_id' => $user->id,
            'body' => 'Source message',
            'is_internal' => false,
        ]);

        $this->actingAs($user)
            ->post("/tickets/{$target->id}/merge", [
                'source_ticket_id' => $source->id,
                'import_conversation' => false,
            ])
            ->assertRedirect("/tickets/{$target->id}");

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $source->id,
            'body' => 'Source message',
        ]);

        $this->assertDatabaseMissing('ticket_messages', [
            'ticket_id' => $target->id,
            'body' => 'Source message',
        ]);
    }

    public function test_user_can_upload_attachment(): void
    {
        Storage::fake('public');
        [$status, $priority] = $this->seedTicketMeta();
        $user = User::factory()->create();
        $ticket = Ticket::query()->create([
            'number' => 'HD-00001',
            'subject' => 'Test',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $this->actingAs($user)
            ->post("/tickets/{$ticket->id}/attachments", [
                'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_attachments', [
            'ticket_id' => $ticket->id,
            'filename' => 'doc.pdf',
        ]);
    }

    public function test_user_can_reply_with_attachment_on_message(): void
    {
        Storage::fake('public');
        [$status, $priority] = $this->seedTicketMeta();
        $user = User::factory()->create();
        $ticket = Ticket::query()->create([
            'number' => 'HD-00001',
            'subject' => 'Test',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $this->actingAs($user)
            ->post("/tickets/{$ticket->id}/reply", [
                'body' => '<p>See attached file.</p>',
                'attachments' => [
                    UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertRedirect();

        $message = TicketMessage::query()->where('ticket_id', $ticket->id)->first();

        $this->assertDatabaseHas('ticket_attachments', [
            'ticket_id' => $ticket->id,
            'ticket_message_id' => $message->id,
            'filename' => 'doc.pdf',
        ]);
    }

    public function test_assignee_options_exclude_customers(): void
    {
        [$status, $priority] = $this->seedTicketMeta();

        $agent = User::factory()->create(['name' => 'Support Agent']);
        $agent->assignRole('agent');

        $customer = User::factory()->customer()->create(['name' => 'Jane Customer']);

        $admin = User::factory()->admin()->create();

        $ticket = Ticket::query()->create([
            'number' => 'HD-00001',
            'subject' => 'Assignee test',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $response = $this->actingAs($admin)
            ->get("/tickets/{$ticket->id}")
            ->assertOk();

        $agentIds = collect($response->original->getData()['page']['props']['agents'])->pluck('id');

        $this->assertTrue($agentIds->contains($agent->id));
        $this->assertFalse($agentIds->contains($customer->id));

        $this->actingAs($admin)
            ->patch("/tickets/{$ticket->id}", [
                'assigned_to' => $customer->id,
            ])
            ->assertSessionHasErrors('assigned_to');
    }

    public function test_api_login_returns_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_api_can_list_tickets_with_token(): void
    {
        [$status, $priority] = $this->seedTicketMeta();
        $user = User::factory()->create(['password' => bcrypt('password')]);

        Ticket::query()->create([
            'number' => 'HD-00001',
            'subject' => 'API ticket',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $login->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/tickets')
            ->assertOk()
            ->assertJsonPath('data.0.subject', 'API ticket');
    }
}
