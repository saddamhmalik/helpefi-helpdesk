<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AvatarSupport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

class ProfileTest extends TenantTestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();
    }

    public function test_user_can_update_profile(): void
    {
        $this->actingAs($this->admin)
            ->tenantPut('/settings/profile', [
                'name' => 'New Name',
                'email' => $this->admin->email,
                'locale' => 'en',
                'timezone' => 'America/New_York',
                'appearance' => 'dark',
                'avatar_type' => 'initials',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'name' => 'New Name',
            'locale' => 'en',
            'timezone' => 'America/New_York',
            'appearance' => 'dark',
        ]);
    }

    public function test_user_can_update_appearance_to_system(): void
    {
        $this->admin->update(['appearance' => 'dark']);

        $this->actingAs($this->admin)
            ->tenantPut('/settings/profile', [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'locale' => 'en',
                'timezone' => '',
                'appearance' => 'system',
                'avatar_type' => 'initials',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'appearance' => 'system',
        ]);
    }

    public function test_user_can_update_locale_and_clear_timezone(): void
    {
        $this->admin->update([
            'locale' => 'en',
            'timezone' => 'Europe/London',
        ]);

        $this->actingAs($this->admin)
            ->tenantPut('/settings/profile', [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'locale' => 'ar',
                'timezone' => '',
                'appearance' => 'system',
                'avatar_type' => 'initials',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'locale' => 'ar',
            'timezone' => null,
        ]);
    }

    public function test_user_can_update_locale_from_navbar_endpoint(): void
    {
        $this->actingAs($this->admin)
            ->tenantPut('/settings/locale', ['locale' => 'de'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'locale' => 'de',
        ]);
    }

    public function test_user_can_switch_avatar_type_to_gravatar(): void
    {
        $this->actingAs($this->admin)
            ->tenantPut('/settings/profile', [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'locale' => 'en',
                'timezone' => '',
                'appearance' => 'system',
                'avatar_type' => 'gravatar',
            ])
            ->assertRedirect();

        $this->admin->refresh();

        $this->assertSame('gravatar', $this->admin->avatar_type);
        $this->assertSame(
            AvatarSupport::gravatarUrl($this->admin->email),
            AvatarSupport::url($this->admin),
        );
    }

    public function test_user_can_upload_avatar(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.jpg', 256, 256);

        $this->actingAs($this->admin)
            ->tenantPost('/settings/profile/avatar', [
                'avatar' => $file,
            ])
            ->assertRedirect();

        $this->admin->refresh();

        $this->assertSame('upload', $this->admin->avatar_type);
        $this->assertNotNull($this->admin->avatar_path);
        Storage::disk('public')->assertExists($this->admin->avatar_path);
    }

    public function test_user_can_remove_uploaded_avatar(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.jpg');

        $this->actingAs($this->admin)
            ->tenantPost('/settings/profile/avatar', [
                'avatar' => $file,
            ])
            ->assertRedirect();

        $this->admin->refresh();
        $storedPath = $this->admin->avatar_path;

        $this->actingAs($this->admin)
            ->tenantDelete('/settings/profile/avatar')
            ->assertRedirect();

        $this->admin->refresh();

        $this->assertSame('initials', $this->admin->avatar_type);
        $this->assertNull($this->admin->avatar_path);
        Storage::disk('public')->assertMissing($storedPath);
    }

    public function test_avatar_upload_rejects_oversized_images(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.jpg', 3000, 3000);

        $this->actingAs($this->admin)
            ->tenantPost('/settings/profile/avatar', [
                'avatar' => $file,
            ])
            ->assertSessionHasErrors('avatar');
    }

    public function test_avatar_upload_rejects_non_image_files(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('avatar.txt', 10, 'text/plain');

        $this->actingAs($this->admin)
            ->tenantPost('/settings/profile/avatar', [
                'avatar' => $file,
            ])
            ->assertSessionHasErrors('avatar');
    }
}
