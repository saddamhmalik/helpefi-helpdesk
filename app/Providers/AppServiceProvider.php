<?php

namespace App\Providers;

use App\Domains\Tenancy\Support\CentralDomain;
use App\Domains\Ai\Clients\HttpAiClient;
use App\Domains\Ai\Clients\OpenAiEmbeddingClient;
use App\Domains\Ai\Contracts\AiCompletionClient;
use App\Domains\Ai\Contracts\AiEmbeddingClient;
use App\Domains\Billing\Contracts\FeatureEntitlementChecker;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Automation\Listeners\BridgeTicketLifecycleToAutomation;
use App\Domains\Automation\Events\TicketAutomationTrigger;
use App\Domains\ServiceDesk\Events\TicketApprovalApproved;
use App\Domains\ServiceDesk\Events\TicketApprovalRejected;
use App\Domains\Tickets\Events\TicketCreated;
use App\Domains\Tickets\Events\TicketCustomerMessageReceived;
use App\Domains\Tickets\Events\TicketUpdated;
use App\Domains\Csat\Observers\TicketCsatObserver;
use App\Domains\Notifications\Listeners\PublishAgentNotificationRealtime;
use App\Domains\Notifications\Observers\TicketNotificationObserver;
use App\Domains\Brands\Models\Brand;
use App\Domains\Channels\Models\Channel;
use App\Domains\Contacts\Models\Organization;
use App\Domains\Contacts\Models\Tag;
use App\Domains\Contacts\Observers\ContactFormReferenceCacheObserver;
use App\Domains\Settings\Models\HelpdeskSetting;
use App\Domains\Settings\Observers\HelpdeskSettingCacheObserver;
use App\Domains\Sla\Models\TicketSlaTimer;
use App\Domains\Sla\Observers\TicketSlaTimerDashboardCacheObserver;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Tickets\Observers\TicketDashboardCacheObserver;
use App\Domains\Tickets\Observers\TicketFormReferenceCacheObserver;
use App\Domains\Tickets\Observers\TicketStatusCacheObserver;
use App\Domains\Workforce\Models\Department;
use App\Domains\Workforce\Models\Team;
use App\Domains\Automation\Listeners\RunAutomationRules;
use App\Domains\Integrations\Listeners\DispatchSlackNotifications;
use App\Domains\Integrations\Listeners\DispatchWebhooks;
use App\Domains\Integrations\Listeners\EnrichTicketFromCrm;
use App\Domains\Integrations\Listeners\SyncExternalIssues;
use App\Domains\Ai\Listeners\TriageTicketOnCreate;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use App\Support\PortalRateLimiters;
use App\Support\QueueConnectionFallback;
use App\Support\SlowQueryLogger;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Domains\Brands\Support\BrandContext::class);
        $this->app->bind(AiCompletionClient::class, HttpAiClient::class);
        $this->app->bind(AiEmbeddingClient::class, OpenAiEmbeddingClient::class);
        $this->app->bind(FeatureEntitlementChecker::class, BillingService::class);
    }

    public function boot(): void
    {
        QueueConnectionFallback::apply();

        $this->registerRouteParameterConstraints();

        SlowQueryLogger::register();

        $bridge = BridgeTicketLifecycleToAutomation::class;

        Event::listen(TicketCreated::class, [$bridge, 'handleCreated']);
        Event::listen(TicketUpdated::class, [$bridge, 'handleUpdated']);
        Event::listen(TicketCustomerMessageReceived::class, [$bridge, 'handleCustomerMessage']);
        Event::listen(TicketApprovalApproved::class, [$bridge, 'handleApprovalApproved']);
        Event::listen(TicketApprovalRejected::class, [$bridge, 'handleApprovalRejected']);

        Event::listen(TicketAutomationTrigger::class, RunAutomationRules::class);
        Event::listen(TicketAutomationTrigger::class, DispatchWebhooks::class);
        Event::listen(TicketAutomationTrigger::class, DispatchSlackNotifications::class);
        Event::listen(TicketAutomationTrigger::class, SyncExternalIssues::class);
        Event::listen(TicketAutomationTrigger::class, EnrichTicketFromCrm::class);
        Event::listen(TicketAutomationTrigger::class, TriageTicketOnCreate::class);
        Event::listen(NotificationSent::class, PublishAgentNotificationRealtime::class);

        Ticket::observe(TicketCsatObserver::class);
        Ticket::observe(TicketNotificationObserver::class);
        Ticket::observe(TicketDashboardCacheObserver::class);

        TicketSlaTimer::observe(TicketSlaTimerDashboardCacheObserver::class);

        TicketStatus::observe(TicketStatusCacheObserver::class);
        Brand::observe(TicketFormReferenceCacheObserver::class);
        Channel::observe(TicketFormReferenceCacheObserver::class);
        Department::observe(TicketFormReferenceCacheObserver::class);
        Team::observe(TicketFormReferenceCacheObserver::class);
        Organization::observe(ContactFormReferenceCacheObserver::class);
        Tag::observe(ContactFormReferenceCacheObserver::class);
        HelpdeskSetting::observe(HelpdeskSettingCacheObserver::class);

        PortalRateLimiters::register();

        RateLimiter::for('tenant-infrastructure-verify', function (Request $request) {
            $limit = max(1, (int) config('tenant_infrastructure.verify_rate_limit_per_minute', 5));

            return Limit::perMinute($limit)->by((string) (
                $request->route('tenant')
                ?? tenant()?->getTenantKey()
                ?? $request->ip()
            ));
        });

        Vite::createAssetPathsUsing(
            fn (string $path, ?bool $secure = null) => '/'.ltrim($path, '/'),
        );

        if ($this->app->runningInConsole()) {
            $this->forceAppUrl();
        } elseif (CentralDomain::isCentralHost(request()->getHost())) {
            $this->forceAppUrl();
        }

        try {
            if (tenancy()->initialized) {
                app(\App\Domains\Channels\Services\OutboundMailService::class)->applyGlobalConfig();
            }
        } catch (\Throwable) {
        }

        if (! $this->app->runningUnitTests() && config('deployment.mode') === 'self_hosted') {
            $license = app(\App\Domains\Platform\Services\HelpefiLicenseService::class);
            $error = $license->resolveValidationError();

            if ($error !== null) {
                report(new \RuntimeException($error));
            }
        }
    }

    private function forceAppUrl(): void
    {
        if ($appUrl = config('app.url')) {
            URL::forceRootUrl($appUrl);

            if ($scheme = parse_url($appUrl, PHP_URL_SCHEME)) {
                URL::forceScheme($scheme);
            }
        }
    }

    private function registerRouteParameterConstraints(): void
    {
        $numericId = '[0-9]+';

        foreach ([
            'ticket',
            'contact',
            'member',
            'asset',
            'organization',
            'article',
            'customer',
            'scan',
            'view',
            'report',
            'notification',
            'approval',
            'sideConversation',
            'entry',
            'incident',
            'issue',
            'user',
            'role',
            'status',
            'policy',
            'target',
            'rule',
            'webhook',
            'inbox',
            'channel',
            'department',
            'team',
            'skill',
            'category',
            'item',
            'businessHours',
            'cannedResponse',
            'template',
            'backup',
            'feedback',
            'slowQuery',
            'registration',
            'testimonial',
            'post',
            'notice',
            'version',
            'rating',
            'collection',
        ] as $parameter) {
            Route::pattern($parameter, $numericId);
        }
    }
}
