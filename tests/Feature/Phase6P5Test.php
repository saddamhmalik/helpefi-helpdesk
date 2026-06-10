<?php

namespace Tests\Feature;

use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Knowledge\Services\KnowledgeService;
use App\Domains\Settings\Models\HelpdeskSetting;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Tickets\Services\TicketBulkService;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TenantBootstrapSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class Phase6P5Test extends TenantTestCase
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

    private function makeTicket(string $subject): Ticket
    {
        $status = TicketStatus::query()->where('slug', 'open')->firstOrFail();
        $priority = TicketPriority::query()->where('slug', 'normal')->firstOrFail();

        return Ticket::query()->create([
            'subject' => $subject,
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);
    }

    public function test_bulk_assign_updates_multiple_tickets(): void
    {
        $agent = User::query()->where('email', 'agent@helpdesk.test')->firstOrFail();
        $tickets = collect([
            $this->makeTicket('Bulk one'),
            $this->makeTicket('Bulk two'),
        ]);

        $result = app(TicketBulkService::class)->execute(
            $tickets->pluck('id')->all(),
            'assign',
            ['assigned_to' => $agent->id],
            $this->admin()->id,
        );

        $this->assertSame(2, $result['updated']);

        foreach ($tickets as $ticket) {
            $this->assertSame($agent->id, $ticket->fresh()->assigned_to);
        }
    }

    public function test_bulk_close_sets_closed_status(): void
    {
        $ticket = $this->makeTicket('Close me');
        $closed = TicketStatus::query()->where('slug', 'closed')->firstOrFail();

        app(TicketBulkService::class)->execute(
            [$ticket->id],
            'close',
            [],
            $this->admin()->id,
        );

        $this->assertSame($closed->id, $ticket->fresh()->ticket_status_id);
    }

    public function test_tickets_bulk_endpoint_accepts_web_request(): void
    {
        $ticket = $this->makeTicket('Web bulk');
        $pending = TicketStatus::query()->where('slug', 'pending')->firstOrFail();

        $this->actingAs($this->admin())
            ->post('/tickets/bulk', [
                'ticket_ids' => [$ticket->id],
                'action' => 'status',
                'ticket_status_id' => $pending->id,
            ])
            ->assertRedirect();

        $this->assertSame($pending->id, $ticket->fresh()->ticket_status_id);
    }

    public function test_article_translation_links_by_group(): void
    {
        HelpdeskSetting::query()->first()?->update([
            'kb_locales' => ['en', 'es'],
            'kb_default_locale' => 'en',
        ]);

        $english = app(KnowledgeService::class)->create([
            'title' => 'Getting started',
            'body' => 'English body',
            'locale' => 'en',
            'is_published' => true,
        ], $this->admin()->id);

        $spanish = app(KnowledgeService::class)->createTranslation($english->id, 'es', [
            'title' => 'Primeros pasos',
            'body' => 'Cuerpo en español',
            'is_published' => true,
        ], $this->admin()->id);

        $this->assertSame($english->translation_group_id, $spanish->translation_group_id);
        $this->assertSame('getting-started', $english->slug);
        $this->assertSame('primeros-pasos', $spanish->slug);

        $translations = app(KnowledgeService::class)->translations($english->id);
        $this->assertCount(2, $translations);
    }

    public function test_published_articles_filter_by_locale(): void
    {
        HelpdeskSetting::query()->first()?->update([
            'kb_locales' => ['en', 'es'],
            'kb_default_locale' => 'en',
        ]);

        KnowledgeArticle::query()->create([
            'title' => 'English only',
            'slug' => 'english-only',
            'locale' => 'en',
            'translation_group_id' => '11111111-1111-1111-1111-111111111111',
            'body' => 'English',
            'is_published' => true,
            'published_at' => now(),
        ]);

        KnowledgeArticle::query()->create([
            'title' => 'Solo español',
            'slug' => 'solo-espanol',
            'locale' => 'es',
            'translation_group_id' => '22222222-2222-2222-2222-222222222222',
            'body' => 'Español',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $englishArticles = app(KnowledgeService::class)->publishedArticles(null, null, 15, null, 'en');
        $titles = collect($englishArticles->items())->pluck('title');

        $this->assertTrue($titles->contains('English only'));
        $this->assertFalse($titles->contains('Solo español'));
    }

    public function test_knowledge_settings_endpoint_updates_locales(): void
    {
        $this->actingAs($this->admin())
            ->putJson('/api/v1/knowledge/settings', [
                'kb_locales' => ['en', 'fr'],
                'kb_default_locale' => 'fr',
            ])
            ->assertOk()
            ->assertJsonPath('kb_default_locale', 'fr');

        $setting = HelpdeskSetting::query()->firstOrFail();
        $this->assertSame(['en', 'fr'], $setting->kb_locales);
        $this->assertSame('fr', $setting->kb_default_locale);
    }
}
