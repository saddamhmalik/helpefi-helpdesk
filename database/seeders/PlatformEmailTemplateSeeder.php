<?php

namespace Database\Seeders;

use App\Domains\Platform\Models\PlatformEmailTemplate;
use Illuminate\Database\Seeder;

class PlatformEmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $brand = config('app.name', 'helpefi');

        PlatformEmailTemplate::query()->updateOrCreate(
            ['slug' => PlatformEmailTemplate::SLUG_REGISTRATION],
            [
                'name' => 'Registration confirmation',
                'subject' => 'Welcome to {{brand}} — we\'re setting up your workspace',
                'body_html' => <<<HTML
<p>Hi {{admin_name}},</p>
<p>Thanks for registering <strong>{{organization_name}}</strong> on {{brand}}. We're provisioning your workspace now.</p>
<p><strong>Workspace URL:</strong> {{workspace_url}}<br>
<strong>Admin email:</strong> {{admin_email}}</p>
<p>Your {{trial_days}}-day free trial starts once setup completes. You'll receive another email with a sign-in link when your workspace is ready.</p>
<p>If you didn't create this account, you can ignore this email.</p>
<p>— The {{brand}} team</p>
HTML,
                'is_active' => true,
                'is_system' => true,
            ],
        );

        PlatformEmailTemplate::query()->updateOrCreate(
            ['slug' => PlatformEmailTemplate::SLUG_WORKSPACE_WELCOME],
            [
                'name' => 'Workspace welcome',
                'subject' => 'Your {{brand}} workspace is ready',
                'body_html' => <<<HTML
<p>Hi {{admin_name}},</p>
<p>Great news — <strong>{{organization_name}}</strong> is ready to use on {{brand}}.</p>
<p>Click the button below to sign in and complete your guided setup:</p>
<p style="margin:28px 0;">
  <a href="{{welcome_url}}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:10px;font-weight:600;">Open my workspace</a>
</p>
<p>Or copy this link: {{welcome_url}}</p>
<p>Your workspace: {{workspace_url}}<br>
Free trial: {{trial_days}} days of full access</p>
<p>— The {{brand}} team</p>
HTML,
                'is_active' => true,
                'is_system' => true,
            ],
        );
    }
}
