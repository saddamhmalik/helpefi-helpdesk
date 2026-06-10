<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($user)
            ->put('/settings/profile', [
                'name' => 'New Name',
                'email' => $user->email,
                'locale' => 'en',
                'timezone' => 'America/New_York',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'locale' => 'en',
            'timezone' => 'America/New_York',
        ]);
    }

    public function test_user_can_update_locale_and_clear_timezone(): void
    {
        $user = User::factory()->create([
            'locale' => 'en',
            'timezone' => 'Europe/London',
        ]);

        $this->actingAs($user)
            ->put('/settings/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => 'ar',
                'timezone' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'locale' => 'ar',
            'timezone' => null,
        ]);
    }
}
