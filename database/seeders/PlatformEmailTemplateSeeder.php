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
                'body_html' => <<<'HTML'
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
            ['slug' => PlatformEmailTemplate::SLUG_REGISTRATION_VERIFICATION],
            [
                'name' => 'Registration verification',
                'subject' => 'Verify your email to create your {{brand}} workspace',
                'body_html' => <<<'HTML'
<p>Hi {{admin_name}},</p>
<p>Thanks for signing up for <strong>{{organization_name}}</strong> on {{brand}}. Confirm your email address to create your workspace.</p>
<p style="margin:28px 0;">
  <a href="{{verification_url}}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:10px;font-weight:600;">Verify email &amp; create workspace</a>
</p>
<p>Or copy this link into your browser:<br>{{verification_url}}</p>
<p>Your workspace and {{trial_days}}-day free trial are created only after you verify. This link expires in 24 hours.</p>
<p>If you didn't request this, you can safely ignore this email.</p>
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
                'body_html' => <<<'HTML'
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

        foreach ($this->trialNurtureTemplates() as $template) {
            PlatformEmailTemplate::query()->updateOrCreate(
                ['slug' => $template['slug']],
                [
                    'name' => $template['name'],
                    'subject' => $template['subject'],
                    'body_html' => $template['body_html'],
                    'is_active' => true,
                    'is_system' => true,
                ],
            );
        }

        foreach ($this->subscriptionEndingTemplates() as $template) {
            PlatformEmailTemplate::query()->updateOrCreate(
                ['slug' => $template['slug']],
                [
                    'name' => $template['name'],
                    'subject' => $template['subject'],
                    'body_html' => $template['body_html'],
                    'is_active' => true,
                    'is_system' => true,
                ],
            );
        }
    }

    private function trialNurtureTemplates(): array
    {
        return [
            [
                'slug' => PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_1,
                'name' => 'Trial nurture — Day 1: Connect channels',
                'subject' => 'Day 1: connect your first support channel on {{brand}}',
                'body_html' => <<<'HTML'
<p>Hi {{admin_name}},</p>
<p>Your <strong>{{organization_name}}</strong> workspace is live. The fastest way to see value today is to connect one inbound channel.</p>
<p style="margin:28px 0;">
  <a href="{{setup_url}}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:10px;font-weight:600;">Open setup wizard</a>
</p>
<p>Start with email or live chat — both create tickets in the same shared inbox. Your workspace: {{workspace_url}}</p>
<p>— The {{brand}} team</p>
HTML,
            ],
            [
                'slug' => PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_3,
                'name' => 'Trial nurture — Day 3: Knowledge base',
                'subject' => 'Publish 5 articles — deflect tickets before they arrive',
                'body_html' => <<<'HTML'
<p>Hi {{admin_name}},</p>
<p>Teams on {{brand}} reduce repeat questions by publishing a small help center early in the trial.</p>
<p>Pick your five most common questions (shipping, billing, access, returns, status) and publish short articles. AI deflection and Copilot use this content immediately.</p>
<p style="margin:28px 0;">
  <a href="{{workspace_url}}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:10px;font-weight:600;">Open workspace</a>
</p>
<p>{{trial_days_remaining}} days left in your trial · Full platform access</p>
<p>— The {{brand}} team</p>
HTML,
            ],
            [
                'slug' => PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_5,
                'name' => 'Trial nurture — Day 5: AI Copilot',
                'subject' => 'Try AI Copilot on a real ticket',
                'body_html' => <<<'HTML'
<p>Hi {{admin_name}},</p>
<p>Open any ticket in {{organization_name}} and launch <strong>Agent Copilot</strong> — summarize the thread, draft a reply, and insert KB suggestions in one click.</p>
<p>AI is included in your trial. Nothing sends to customers until your agents approve it.</p>
<p style="margin:28px 0;">
  <a href="{{workspace_url}}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:10px;font-weight:600;">Try Copilot now</a>
</p>
<p>— The {{brand}} team</p>
HTML,
            ],
            [
                'slug' => PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_7,
                'name' => 'Trial nurture — Day 7: Invite team',
                'subject' => 'Invite your team — split the queue',
                'body_html' => <<<'HTML'
<p>Hi {{admin_name}},</p>
<p>Support workflows click when more than one agent works from the same inbox with SLAs and assignments.</p>
<p>Invite teammates from Settings → Members, set your first SLA policy, and route tickets by team or skill.</p>
<p style="margin:28px 0;">
  <a href="{{workspace_url}}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:10px;font-weight:600;">Invite agents</a>
</p>
<p>{{trial_days_remaining}} days remaining in your {{trial_days}}-day trial</p>
<p>— The {{brand}} team</p>
HTML,
            ],
            [
                'slug' => PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_10,
                'name' => 'Trial nurture — Day 10: Switch story',
                'subject' => 'Teams switch when one inbox beats five tabs',
                'body_html' => <<<'HTML'
<p>Hi {{admin_name}},</p>
<p>Most teams evaluate {{brand}} against Zendesk, Freshdesk, or a shared inbox plus spreadsheets.</p>
<p>Run us in parallel for a week: same email forwarded to {{organization_name}}, compare handle time and deflection with your current stack.</p>
<p style="margin:28px 0;">
  <a href="{{pricing_url}}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:10px;font-weight:600;">View pricing</a>
</p>
<p>Questions? Reply to this email or visit {{workspace_url}}</p>
<p>— The {{brand}} team</p>
HTML,
            ],
            [
                'slug' => PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_12,
                'name' => 'Trial nurture — Day 12: Plan selection',
                'subject' => '{{trial_days_remaining}} days left — pick a plan that fits',
                'body_html' => <<<'HTML'
<p>Hi {{admin_name}},</p>
<p>Your {{brand}} trial for <strong>{{organization_name}}</strong> ends in {{trial_days_remaining}} days.</p>
<p>Professional includes automation, SLA, live chat, and service catalog. Enterprise adds AI, integrations, SSO, and custom domain — or add AI Copilot à la carte on Professional.</p>
<p style="margin:28px 0;">
  <a href="{{billing_url}}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:10px;font-weight:600;">Choose a plan</a>
</p>
<p>Compare plans: {{pricing_url}}</p>
<p>— The {{brand}} team</p>
HTML,
            ],
            [
                'slug' => PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_13,
                'name' => 'Trial nurture — Day 13: Last day',
                'subject' => 'Last day of your {{brand}} trial',
                'body_html' => <<<'HTML'
<p>Hi {{admin_name}},</p>
<p>Your full-access trial for <strong>{{organization_name}}</strong> ends tomorrow.</p>
<p>Keep your workspace, tickets, and KB live by selecting a plan today — upgrade takes minutes from billing settings.</p>
<p style="margin:28px 0;">
  <a href="{{billing_url}}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:10px;font-weight:600;">Upgrade now</a>
</p>
<p>Need help choosing? Reply to this email — we respond within one business day.</p>
<p>— The {{brand}} team</p>
HTML,
            ],
        ];
    }

    private function subscriptionEndingTemplates(): array
    {
        return [
            [
                'slug' => PlatformEmailTemplate::SLUG_SUBSCRIPTION_ENDING_7_DAYS,
                'name' => 'Subscription ending — 7 days left',
                'subject' => 'Your {{brand}} subscription ends in 7 days',
                'body_html' => <<<'HTML'
<p>Hi {{admin_name}},</p>
<p>Your paid subscription for <strong>{{organization_name}}</strong> is scheduled to end on {{access_ends_at}}.</p>
<p>After that date, workspace access will be restricted unless you renew or reactivate your plan.</p>
<p style="margin:28px 0;">
  <a href="{{billing_url}}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:10px;font-weight:600;">Manage billing</a>
</p>
<p>— The {{brand}} team</p>
HTML,
            ],
            [
                'slug' => PlatformEmailTemplate::SLUG_SUBSCRIPTION_ENDING_3_DAYS,
                'name' => 'Subscription ending — 3 days left',
                'subject' => '3 days left on your {{brand}} subscription',
                'body_html' => <<<'HTML'
<p>Hi {{admin_name}},</p>
<p>Access for <strong>{{organization_name}}</strong> ends on {{access_ends_at}} — {{grace_days_remaining}} days from now.</p>
<p>Reactivate your subscription from billing settings to keep your workspace, tickets, and knowledge base available without interruption.</p>
<p style="margin:28px 0;">
  <a href="{{billing_url}}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:10px;font-weight:600;">Reactivate subscription</a>
</p>
<p>— The {{brand}} team</p>
HTML,
            ],
            [
                'slug' => PlatformEmailTemplate::SLUG_SUBSCRIPTION_ENDING_1_DAY,
                'name' => 'Subscription ending — 1 day left',
                'subject' => 'Tomorrow is the last day of your {{brand}} subscription',
                'body_html' => <<<'HTML'
<p>Hi {{admin_name}},</p>
<p>Your {{brand}} subscription for <strong>{{organization_name}}</strong> ends tomorrow ({{access_ends_at}}).</p>
<p>Renew today to avoid losing agent access and inbound channel routing.</p>
<p style="margin:28px 0;">
  <a href="{{billing_url}}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:10px;font-weight:600;">Renew now</a>
</p>
<p>— The {{brand}} team</p>
HTML,
            ],
            [
                'slug' => PlatformEmailTemplate::SLUG_SUBSCRIPTION_ENDING_FINAL,
                'name' => 'Subscription ending — last day',
                'subject' => 'Final day of access for {{organization_name}}',
                'body_html' => <<<'HTML'
<p>Hi {{admin_name}},</p>
<p>Today is the last day of paid access for <strong>{{organization_name}}</strong> on {{brand}}.</p>
<p>Your workspace will become read-only and agents will lose access after {{access_ends_at}} unless you renew.</p>
<p style="margin:28px 0;">
  <a href="{{billing_url}}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:10px;font-weight:600;">Renew subscription</a>
</p>
<p>Need help? Reply to this email — we respond within one business day.</p>
<p>— The {{brand}} team</p>
HTML,
            ],
        ];
    }
}
