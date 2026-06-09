<?php

namespace Tests\Feature;

use App\Domains\Knowledge\Models\KbDeflectionEvent;
use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Knowledge\Models\KnowledgeCollection;
use App\Domains\Settings\Models\HelpdeskSetting;
use App\Domains\Tickets\Models\Ticket;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KbDeflectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class]);
        HelpdeskSetting::query()->create(['kb_deflection_enabled' => true]);
    }

    private function createArticle(string $title, string $slug, string $body): KnowledgeArticle
    {
        $default = \App\Domains\Brands\Models\Brand::query()->where('slug', 'default')->firstOrFail();

        $collection = KnowledgeCollection::query()->create([
            'brand_id' => $default->id,
            'name' => 'Help',
            'slug' => 'help-'.$slug,
            'is_public' => true,
        ]);

        return KnowledgeArticle::query()->create([
            'knowledge_collection_id' => $collection->id,
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $body,
            'body' => $body,
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function test_kb_suggest_returns_ranked_articles(): void
    {
        $this->createArticle('Reset your password', 'reset-password', 'Use the forgot password link on the login page.');
        $this->createArticle('Billing overview', 'billing', 'Manage invoices and payment methods.');

        $response = $this->postJson('/api/v1/portal/default/kb-suggest', [
            'subject' => 'password reset',
            'description' => 'I forgot my login password',
        ])->assertOk();

        $this->assertNotEmpty($response->json('session_id'));
        $this->assertSame('Reset your password', $response->json('articles.0.title'));
        $this->assertDatabaseHas('kb_deflection_events', [
            'event_type' => KbDeflectionEvent::EVENT_SUGGESTIONS_SHOWN,
        ]);
    }

    public function test_kb_deflect_records_deflected_event(): void
    {
        $article = $this->createArticle('VPN setup', 'vpn-setup', 'Connect to corporate VPN.');

        $suggest = $this->postJson('/api/v1/portal/default/kb-suggest', [
            'subject' => 'VPN setup help',
        ])->assertOk();

        $this->postJson('/api/v1/portal/default/kb-deflect', [
            'session_id' => $suggest->json('session_id'),
            'article_id' => $article->id,
        ])->assertOk();

        $this->assertDatabaseHas('kb_deflection_events', [
            'session_id' => $suggest->json('session_id'),
            'event_type' => KbDeflectionEvent::EVENT_DEFLECTED,
            'article_id' => $article->id,
        ]);
    }

    public function test_portal_submit_records_ticket_with_kb_session(): void
    {
        $this->createArticle('Email not syncing', 'email-sync', 'Check IMAP settings and reconnect your mailbox.');

        $suggest = $this->postJson('/api/v1/portal/default/kb-suggest', [
            'subject' => 'Email not syncing',
            'description' => 'Outlook stopped receiving mail',
        ])->assertOk();

        $this->post('/portal/default/submit', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'Email not syncing',
            'description' => 'Outlook stopped receiving mail',
            'kb_session_id' => $suggest->json('session_id'),
        ])->assertRedirect();

        $ticket = Ticket::query()->where('subject', 'Email not syncing')->first();

        $this->assertNotNull($ticket);
        $this->assertDatabaseHas('kb_deflection_events', [
            'session_id' => $suggest->json('session_id'),
            'event_type' => KbDeflectionEvent::EVENT_TICKET_CREATED,
            'ticket_id' => $ticket->id,
        ]);
    }

    public function test_kb_deflection_disabled_returns_empty_suggestions(): void
    {
        HelpdeskSetting::query()->first()->update(['kb_deflection_enabled' => false]);
        $this->createArticle('Reset your password', 'reset-password', 'Password reset steps.');

        $response = $this->postJson('/api/v1/portal/default/kb-suggest', [
            'subject' => 'password reset',
        ])->assertOk();

        $this->assertFalse($response->json('enabled'));
        $this->assertSame([], $response->json('articles'));
        $this->assertDatabaseCount('kb_deflection_events', 0);
    }

    public function test_dashboard_includes_kb_deflection_metrics(): void
    {
        $user = User::factory()->create();
        $this->createArticle('Reset your password', 'reset-password', 'Password reset steps.');

        $suggest = $this->postJson('/api/v1/portal/default/kb-suggest', [
            'subject' => 'password reset',
        ])->assertOk();

        $this->postJson('/api/v1/portal/default/kb-deflect', [
            'session_id' => $suggest->json('session_id'),
        ])->assertOk();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Index')
                ->where('kbDeflection.suggestions_shown', 1)
                ->where('kbDeflection.deflected', 1));
    }
}
