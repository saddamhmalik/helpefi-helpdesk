<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Mail\PlatformTemplateMail;
use App\Domains\Platform\Models\PlatformEmailTemplate;
use App\Domains\Tenancy\Services\CentralSettingsService;
use App\Domains\Tenancy\Services\TenantDomainService;
use App\Models\Tenant;
use Database\Seeders\PlatformEmailTemplateSeeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PlatformMailService
{
    public function __construct(
        private PlatformEmailTemplateService $templates,
        private CentralSettingsService $settings,
    ) {}

    public function sendRegistrationConfirmation(Tenant $tenant, string $adminName, string $adminEmail): void
    {
        $this->send(
            PlatformEmailTemplate::SLUG_REGISTRATION,
            $adminEmail,
            $this->variables($tenant, $adminName, $adminEmail),
        );
    }

    public function sendRegistrationVerification(string $organizationName, string $adminName, string $adminEmail, string $verificationUrl): void
    {
        $this->send(PlatformEmailTemplate::SLUG_REGISTRATION_VERIFICATION, $adminEmail, [
            'brand' => config('app.name', 'helpefi'),
            'admin_name' => $adminName,
            'admin_email' => $adminEmail,
            'organization_name' => $organizationName,
            'verification_url' => $verificationUrl,
            'trial_days' => (string) $this->settings->trialDays(),
            'central_domain' => config('tenancy.central_app_domain'),
        ]);
    }

    public function sendWorkspaceWelcome(Tenant $tenant, string $adminName, string $adminEmail, string $welcomeUrl): void
    {
        $variables = $this->variables($tenant, $adminName, $adminEmail);
        $variables['welcome_url'] = $welcomeUrl;

        $this->send(
            PlatformEmailTemplate::SLUG_WORKSPACE_WELCOME,
            $adminEmail,
            $variables,
        );
    }

    public function send(string $slug, string $to, array $variables): void
    {
        $this->ensureSystemTemplates();

        $rendered = $this->templates->render($slug, $variables);

        if (! $rendered) {
            Log::warning('Platform email not sent: template missing or inactive.', [
                'slug' => $slug,
                'to' => $to,
            ]);

            return;
        }

        if (config('mail.default') === 'log') {
            Log::info('Platform email queued to log mailer (not delivered to inbox).', [
                'slug' => $slug,
                'to' => $to,
            ]);
        }

        try {
            Mail::send(new PlatformTemplateMail(
                recipientEmail: $to,
                mailSubject: $rendered['subject'],
                bodyHtml: $rendered['body_html'],
            ));
        } catch (Throwable $exception) {
            Log::error('Platform email send failed.', [
                'slug' => $slug,
                'to' => $to,
                'mailer' => config('mail.default'),
                'message' => $exception->getMessage(),
            ]);

            report($exception);
        }
    }

    private function ensureSystemTemplates(): void
    {
        $required = [
            PlatformEmailTemplate::SLUG_REGISTRATION,
            PlatformEmailTemplate::SLUG_REGISTRATION_VERIFICATION,
            PlatformEmailTemplate::SLUG_WORKSPACE_WELCOME,
        ];

        $existingSlugs = PlatformEmailTemplate::query()
            ->whereIn('slug', $required)
            ->pluck('slug')
            ->all();

        if (! array_diff($required, $existingSlugs)) {
            return;
        }

        app(PlatformEmailTemplateSeeder::class)->run();
    }

    private function variables(Tenant $tenant, string $adminName, string $adminEmail): array
    {
        return [
            'brand' => config('app.name', 'helpefi'),
            'admin_name' => $adminName,
            'admin_email' => $adminEmail,
            'organization_name' => $tenant->name,
            'workspace_slug' => $tenant->slug,
            'workspace_url' => app(TenantDomainService::class)->primaryUrl($tenant) ?? '',
            'welcome_url' => '',
            'trial_days' => (string) $this->settings->trialDays(),
            'central_domain' => config('tenancy.central_app_domain'),
        ];
    }
}
