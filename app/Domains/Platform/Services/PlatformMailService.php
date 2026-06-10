<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Mail\PlatformTemplateMail;
use App\Domains\Tenancy\Services\CentralSettingsService;
use App\Domains\Tenancy\Services\TenantDomainService;
use App\Models\Tenant;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PlatformMailService
{
    public function __construct(
        private PlatformEmailTemplateService $templates,
        private CentralSettingsService $settings,
    ) {
    }

    public function sendRegistrationConfirmation(Tenant $tenant, string $adminName, string $adminEmail): void
    {
        $this->send(
            \App\Domains\Platform\Models\PlatformEmailTemplate::SLUG_REGISTRATION,
            $adminEmail,
            $this->variables($tenant, $adminName, $adminEmail),
        );
    }

    public function sendWorkspaceWelcome(Tenant $tenant, string $adminName, string $adminEmail, string $welcomeUrl): void
    {
        $variables = $this->variables($tenant, $adminName, $adminEmail);
        $variables['welcome_url'] = $welcomeUrl;

        $this->send(
            \App\Domains\Platform\Models\PlatformEmailTemplate::SLUG_WORKSPACE_WELCOME,
            $adminEmail,
            $variables,
        );
    }

    public function send(string $slug, string $to, array $variables): void
    {
        $rendered = $this->templates->render($slug, $variables);

        if (! $rendered) {
            return;
        }

        try {
            Mail::send(new PlatformTemplateMail(
                recipientEmail: $to,
                mailSubject: $rendered['subject'],
                bodyHtml: $rendered['body_html'],
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
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
