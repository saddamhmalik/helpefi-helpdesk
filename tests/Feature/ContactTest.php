<?php

namespace Tests\Feature;

use App\Domains\Contacts\Models\Contact;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_contacts_index_shows_portal_access_state(): void
    {
        $admin = User::factory()->admin()->create();

        $guest = Contact::query()->create([
            'name' => 'Guest User',
            'email' => 'guest@example.com',
        ]);

        $portalContact = Contact::query()->create([
            'name' => 'Portal User',
            'email' => 'portal@example.com',
        ]);

        User::factory()->customer()->create([
            'name' => 'Portal User',
            'email' => 'portal@example.com',
            'contact_id' => $portalContact->id,
        ]);

        $this->actingAs($admin)
            ->get('/contacts')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Contacts/Index')
                ->has('contacts.data', 2)
                ->has('stats')
                ->where('stats.portal', 1)
                ->where('stats.guest', 1));

        $this->actingAs($admin)
            ->get('/contacts?access=portal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('contacts.data', 1)
                ->where('contacts.data.0.id', $portalContact->id));

        $this->actingAs($admin)
            ->get('/contacts?access=guest')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('contacts.data', 1)
                ->where('contacts.data.0.id', $guest->id));
    }

    public function test_customer_accounts_route_redirects_to_contacts_filter(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/customers/accounts')
            ->assertRedirect(route('contacts.index', ['access' => 'portal']));
    }
}
