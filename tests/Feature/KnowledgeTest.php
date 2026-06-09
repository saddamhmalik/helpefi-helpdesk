<?php

namespace Tests\Feature;

use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Knowledge\Models\KnowledgeCollection;
use App\Domains\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_home_is_public(): void
    {
        $this->get('/portal/default')->assertOk();
    }

    public function test_agent_can_create_collection(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/knowledge/collections', [
                'name' => 'Billing',
                'description' => 'Billing help',
                'is_public' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('knowledge_collections', ['name' => 'Billing']);
    }

    public function test_article_update_creates_version(): void
    {
        $user = User::factory()->create();
        $article = KnowledgeArticle::query()->create([
            'author_id' => $user->id,
            'title' => 'Original',
            'slug' => 'original',
            'body' => 'Original body',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($user)
            ->put("/knowledge/{$article->id}", [
                'title' => 'Updated',
                'body' => 'Updated body',
                'is_published' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('knowledge_article_versions', [
            'knowledge_article_id' => $article->id,
            'title' => 'Original',
            'version_number' => 1,
        ]);
    }

    public function test_portal_submit_creates_ticket(): void
    {
        $this->seed(\Database\Seeders\TicketLookupSeeder::class);

        $this->post('/portal/default/submit', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'Need help',
            'description' => 'Something is broken',
        ])->assertRedirect();

        $this->assertDatabaseHas('tickets', ['subject' => 'Need help']);
        $this->assertDatabaseHas('contacts', ['email' => 'john@example.com']);
    }

    public function test_portal_track_finds_ticket_by_number_and_email(): void
    {
        $this->seed(\Database\Seeders\TicketLookupSeeder::class);

        $this->post('/portal/default/submit', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'Track me',
            'description' => 'Test',
        ]);

        $ticket = Ticket::query()->where('subject', 'Track me')->first();

        $this->get('/portal/default/track?number='.$ticket->number.'&email=jane@example.com')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Track')
                ->where('ticket.subject', 'Track me'));
    }

    public function test_published_article_visible_on_portal(): void
    {
        $default = \App\Domains\Brands\Models\Brand::query()->where('slug', 'default')->firstOrFail();

        $collection = KnowledgeCollection::query()->create([
            'brand_id' => $default->id,
            'name' => 'Help',
            'slug' => 'help',
            'is_public' => true,
        ]);

        $this->assertSame($default->id, $collection->brand_id);

        KnowledgeArticle::query()->create([
            'knowledge_collection_id' => $collection->id,
            'title' => 'Public article',
            'slug' => 'public-article',
            'body' => 'Content here',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->get('/portal/default/articles/public-article')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Article')
                ->where('article.title', 'Public article'));
    }

    public function test_api_portal_submit_returns_ticket_number(): void
    {
        $this->seed(\Database\Seeders\TicketLookupSeeder::class);

        $this->postJson('/api/v1/portal/default/tickets', [
            'name' => 'API User',
            'email' => 'api@example.com',
            'subject' => 'API ticket',
            'description' => 'From API',
        ])->assertCreated()
            ->assertJsonStructure(['number', 'subject', 'status']);
    }

    public function test_product_knowledge_seeder_creates_documentation(): void
    {
        User::factory()->create(['email' => 'admin@helpdesk.test']);

        $this->seed(\Database\Seeders\ProductKnowledgeSeeder::class);

        $this->assertDatabaseHas('knowledge_collections', ['slug' => 'product-guide']);
        $this->assertDatabaseHas('knowledge_collections', ['slug' => 'customer-self-service']);
        $this->assertDatabaseHas('knowledge_articles', ['slug' => 'helpdesk-platform-overview']);
        $this->assertDatabaseHas('knowledge_articles', ['slug' => 'how-to-submit-a-support-request']);

        $this->get('/portal/default/articles/how-to-submit-a-support-request')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Article')
                ->where('article.title', 'How to submit a support request'));
    }
}
