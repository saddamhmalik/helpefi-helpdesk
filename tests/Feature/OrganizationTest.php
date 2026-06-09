<?php

namespace Tests\Feature;

use App\Domains\Contacts\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_organization(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/organizations', [
                'name' => 'Acme Corp',
                'domains' => ['acme.com'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('organizations', ['name' => 'Acme Corp']);
        $this->assertDatabaseHas('organization_domains', ['domain' => 'acme.com']);
    }

    public function test_contact_auto_links_to_organization_by_email_domain(): void
    {
        $user = User::factory()->create();
        $org = Organization::query()->create(['name' => 'Acme']);
        $org->domains()->create(['domain' => 'example.com']);

        $this->actingAs($user)
            ->post('/contacts', [
                'name' => 'Jane',
                'email' => 'jane@example.com',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('contacts', [
            'email' => 'jane@example.com',
            'organization_id' => $org->id,
        ]);
    }
}
