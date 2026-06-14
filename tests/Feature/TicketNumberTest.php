<?php

namespace Tests\Feature;

use App\Domains\Brands\Models\Brand;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Tickets\Repositories\TicketRepository;
use Database\Seeders\TenantBootstrapSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class TicketNumberTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            TenantBootstrapSeeder::class,
            TicketLookupSeeder::class,
        ]);
    }

    public function test_ticket_number_sequence_is_global_for_shared_prefix(): void
    {
        $openStatus = TicketStatus::query()->where('slug', 'open')->firstOrFail();
        $normalPriority = TicketPriority::query()->where('slug', 'normal')->firstOrFail();
        $brand = Brand::query()->create([
            'name' => 'Support Co',
            'slug' => 'support-co',
            'is_active' => true,
        ]);

        Ticket::query()->create([
            'number' => 'HD-00001',
            'subject' => 'Existing ticket',
            'ticket_status_id' => $openStatus->id,
            'ticket_priority_id' => $normalPriority->id,
        ]);

        Ticket::query()->create([
            'number' => 'HD-00002',
            'subject' => 'Another ticket',
            'ticket_status_id' => $openStatus->id,
            'ticket_priority_id' => $normalPriority->id,
        ]);

        $ticket = app(TicketRepository::class)->create([
            'subject' => 'Branded inbound',
            'brand_id' => $brand->id,
            'ticket_status_id' => $openStatus->id,
            'ticket_priority_id' => $normalPriority->id,
        ]);

        $this->assertSame('HD-00003', $ticket->number);
    }
}
