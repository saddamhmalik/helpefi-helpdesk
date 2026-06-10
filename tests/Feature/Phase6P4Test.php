<?php

namespace Tests\Feature;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Services\ContactTimelineService;
use App\Domains\Csat\Models\CsatResponse;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TenantBootstrapSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class Phase6P4Test extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            TenantBootstrapSeeder::class,
            TicketLookupSeeder::class,
            ChannelSeeder::class,
            SlaSeeder::class,
        ]);
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();
    }

    public function test_contact_timeline_aggregates_customer_history(): void
    {
        $status = TicketStatus::query()->where('slug', 'open')->firstOrFail();
        $priority = TicketPriority::query()->where('slug', 'normal')->firstOrFail();
        $contact = Contact::query()->create(['name' => 'Taylor', 'email' => 'taylor@example.com']);

        $ticket = Ticket::query()->create([
            'subject' => 'Billing question',
            'contact_id' => $contact->id,
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        TicketMessage::query()->create([
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'body' => 'Can you help with my invoice?',
            'is_internal' => false,
        ]);

        CsatResponse::query()->create([
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'rating' => 5,
            'comment' => 'Great support',
            'channel' => CsatResponse::CHANNEL_PORTAL,
        ]);

        $timeline = app(ContactTimelineService::class)->forContact($contact->id);
        $types = collect($timeline)->pluck('type');

        $this->assertTrue($types->contains('ticket_opened'));
        $this->assertTrue($types->contains('customer_message'));
        $this->assertTrue($types->contains('csat'));
    }

    public function test_contact_show_includes_timeline(): void
    {
        $contact = Contact::query()->create(['name' => 'Jordan', 'email' => 'jordan@example.com']);

        $this->actingAs($this->admin())
            ->tenantGet("/contacts/{$contact->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Contacts/Show')
                ->has('timeline'));
    }

    public function test_api_contact_timeline_endpoint(): void
    {
        $contact = Contact::query()->create(['name' => 'Sam', 'email' => 'sam@example.com']);

        $token = $this->tenantPostJson('/api/v1/auth/login', [
            'email' => $this->admin()->email,
            'password' => 'password',
        ])->json('token');

        $this->withToken($token)
            ->tenantGetJson("/api/v1/contacts/{$contact->id}/timeline")
            ->assertOk()
            ->assertJsonStructure(['timeline']);
    }
}
