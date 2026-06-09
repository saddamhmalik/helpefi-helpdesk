<?php

namespace Tests\Feature;

use App\Domains\Brands\Models\Brand;
use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Knowledge\Models\KnowledgeCollection;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiBrandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class, \Database\Seeders\PermissionSeeder::class]);
    }

    private function defaultBrand(): Brand
    {
        return Brand::query()->where('slug', 'default')->firstOrFail();
    }

    public function test_portal_redirects_to_default_brand(): void
    {
        $this->get('/portal')
            ->assertRedirect('/portal/default');
    }

    public function test_brand_portal_shows_only_brand_collections(): void
    {
        $default = $this->defaultBrand();

        $acme = Brand::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'portal_title' => 'Acme Support',
            'is_active' => true,
        ]);

        $defaultCollection = KnowledgeCollection::query()->create([
            'brand_id' => $default->id,
            'name' => 'Default Help',
            'slug' => 'default-help',
            'is_public' => true,
        ]);

        $acmeCollection = KnowledgeCollection::query()->create([
            'brand_id' => $acme->id,
            'name' => 'Acme Docs',
            'slug' => 'acme-docs',
            'is_public' => true,
        ]);

        KnowledgeArticle::query()->create([
            'knowledge_collection_id' => $defaultCollection->id,
            'title' => 'Default article',
            'slug' => 'default-article',
            'body' => 'Default content',
            'is_published' => true,
            'published_at' => now(),
        ]);

        KnowledgeArticle::query()->create([
            'knowledge_collection_id' => $acmeCollection->id,
            'title' => 'Acme article',
            'slug' => 'acme-article',
            'body' => 'Acme content',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->get('/portal/acme')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Index')
                ->has('collections', 1)
                ->where('collections.0.slug', 'acme-docs'));
    }

    public function test_portal_submit_uses_brand_ticket_prefix_and_priority(): void
    {
        $urgent = TicketPriority::query()->where('slug', 'urgent')->first()
            ?? TicketPriority::query()->first();

        $brand = Brand::query()->create([
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'ticket_number_prefix' => 'ENT-',
            'default_ticket_priority_id' => $urgent->id,
            'is_active' => true,
        ]);

        $this->post('/portal/enterprise/submit', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'Enterprise issue',
            'description' => 'Need help',
        ])->assertRedirect();

        $ticket = Ticket::query()->where('subject', 'Enterprise issue')->first();

        $this->assertNotNull($ticket);
        $this->assertSame($brand->id, $ticket->brand_id);
        $this->assertSame($urgent->id, $ticket->ticket_priority_id);
        $this->assertStringStartsWith('ENT-', $ticket->number);
    }

    public function test_inbound_email_assigns_inbox_brand_to_ticket(): void
    {
        $brand = Brand::query()->create([
            'name' => 'Support Co',
            'slug' => 'support-co',
            'is_active' => true,
        ]);

        $inbox = EmailInbox::query()->create([
            'brand_id' => $brand->id,
            'name' => 'Support',
            'address' => 'help@supportco.test',
            'inbound_token' => 'test-inbound-token',
            'is_active' => true,
            'inbound_method' => 'webhook',
        ]);

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'user@example.com',
            'from_name' => 'User',
            'to_email' => 'help@supportco.test',
            'subject' => 'Email help',
            'body' => 'Please assist',
        ], ['X-Channel-Token' => $inbox->inbound_token])
            ->assertCreated();

        $ticket = Ticket::query()->where('subject', 'Email help')->first();

        $this->assertNotNull($ticket);
        $this->assertSame($brand->id, $ticket->brand_id);
        $this->assertSame($inbox->id, $ticket->email_inbox_id);
    }

    public function test_admin_can_manage_brands(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/settings/brands')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/Brands'));

        $this->actingAs($user)
            ->post('/settings/brands', [
                'name' => 'Globex',
                'slug' => 'globex',
                'portal_title' => 'Globex Help',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('brands', ['slug' => 'globex', 'portal_title' => 'Globex Help']);
    }
}
