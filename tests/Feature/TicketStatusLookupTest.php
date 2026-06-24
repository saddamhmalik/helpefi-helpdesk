<?php

namespace Tests\Feature;

use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Tickets\Services\TicketStatusLookup;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TenantTestCase;

class TicketStatusLookupTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TicketLookupSeeder::class);
        TicketStatusLookup::forget();
    }

    public function test_lookup_returns_closed_and_open_ids(): void
    {
        $lookup = app(TicketStatusLookup::class);

        $firstClosedId = TicketStatus::query()
            ->where('is_closed', true)
            ->orderBy('sort_order')
            ->value('id');
        $openId = TicketStatus::query()->where('slug', 'open')->value('id');
        $closedSlugId = TicketStatus::query()->where('slug', 'closed')->value('id');

        $this->assertSame($firstClosedId, $lookup->firstClosedId());
        $this->assertSame($openId, $lookup->firstOpenId());
        $this->assertTrue($lookup->closedIds()->contains($closedSlugId));
        $this->assertSame('closed', $lookup->defaultClosed()->slug);
        $this->assertSame('open', $lookup->defaultOpen()?->slug);
        $this->assertTrue($lookup->openIds()->contains($openId));
        $this->assertFalse($lookup->openIds()->contains($closedSlugId));
        $this->assertTrue($lookup->isClosedId($closedSlugId));
        $this->assertFalse($lookup->isClosedId($openId));
    }

    public function test_forget_refreshes_cached_status_ids(): void
    {
        $lookup = app(TicketStatusLookup::class);
        $lookup->firstClosedId();

        TicketStatusLookup::forget();

        TicketStatus::query()->where('slug', 'pending')->update(['is_closed' => true]);

        $this->assertTrue($lookup->closedIds()->contains(
            TicketStatus::query()->where('slug', 'pending')->value('id'),
        ));
    }

    public function test_restrict_to_open_tickets_filters_by_open_status_ids(): void
    {
        $lookup = app(TicketStatusLookup::class);
        $openId = TicketStatus::query()->where('slug', 'open')->value('id');
        $closedId = TicketStatus::query()->where('slug', 'closed')->value('id');
        $priorityId = TicketPriority::query()->where('slug', 'normal')->value('id');

        Ticket::query()->create([
            'number' => 'HD-20001',
            'subject' => 'Open ticket',
            'ticket_status_id' => $openId,
            'ticket_priority_id' => $priorityId,
        ]);
        Ticket::query()->create([
            'number' => 'HD-20002',
            'subject' => 'Closed ticket',
            'ticket_status_id' => $closedId,
            'ticket_priority_id' => $priorityId,
        ]);

        $openCount = Ticket::query()
            ->tap(fn ($query) => $lookup->restrictToOpenTickets($query))
            ->count();

        $this->assertSame(1, $openCount);
    }

    public function test_sum_open_tickets_select_handles_cached_id_shapes(): void
    {
        $lookup = app(TicketStatusLookup::class);
        $openId = TicketStatus::query()->where('slug', 'open')->value('id');

        Cache::put(
            \App\Support\TenantCache::key('ticket_status.open_ids'),
            [['id' => $openId]],
            3600,
        );

        $sql = $lookup->sumOpenTicketsSelect();

        $this->assertStringContainsString((string) $openId, $sql);
        $this->assertStringNotContainsString('Array', $sql);
    }
}
