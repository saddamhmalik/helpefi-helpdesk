<?php

namespace Tests\Feature;

use App\Domains\Platform\Models\PlatformFeedback;
use App\Models\User;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class PlatformFeedbackTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();
    }

    private function centralAdminLogin(): void
    {
        $this->post('http://'.config('tenancy.central_app_domain').'/admin/login', [
            'email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'password' => PlatformUserSeeder::DEFAULT_PASSWORD,
        ]);
    }

    public function test_tenant_admin_can_submit_platform_feedback(): void
    {
        $this->actingAs($this->admin())
            ->tenantGet('/settings/platform-feedback')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/PlatformFeedback'));

        $this->actingAs($this->admin())
            ->tenantPost('/settings/platform-feedback', [
                'type' => PlatformFeedback::TYPE_FEATURE_REQUEST,
                'subject' => 'Dark mode for agent UI',
                'body' => 'Please add a dark theme option for agents.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('platform_feedback', [
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
            'user_email' => 'admin@helpdesk.test',
            'type' => PlatformFeedback::TYPE_FEATURE_REQUEST,
            'subject' => 'Dark mode for agent UI',
            'status' => PlatformFeedback::STATUS_OPEN,
        ], 'central');
    }

    public function test_platform_admin_can_view_and_update_feedback(): void
    {
        PlatformFeedback::query()->create([
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
            'user_id' => 1,
            'user_name' => 'Admin User',
            'user_email' => 'admin@helpdesk.test',
            'type' => PlatformFeedback::TYPE_FEEDBACK,
            'subject' => 'Great product',
            'body' => 'Love the workspace inbox.',
            'status' => PlatformFeedback::STATUS_OPEN,
        ]);

        $this->centralAdminLogin();

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/feedback')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Admin/Feedback/Index')
                ->has('submissions.data', 1)
                ->where('submissions.data.0.subject', 'Great product')
                ->where('submissions.data.0.tenant_name', $this->tenant->name));

        $feedbackId = PlatformFeedback::query()->value('id');

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/feedback/'.$feedbackId)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Admin/Feedback/Show')
                ->where('submission.subject', 'Great product')
                ->where('submission.tenant_id', $this->tenant->id));

        $this->put('http://'.config('tenancy.central_app_domain').'/admin/feedback/'.$feedbackId.'/status', [
            'status' => PlatformFeedback::STATUS_REVIEWED,
        ])->assertRedirect();

        $this->assertDatabaseHas('platform_feedback', [
            'id' => $feedbackId,
            'status' => PlatformFeedback::STATUS_REVIEWED,
        ], 'central');
    }
}
