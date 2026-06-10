<?php

namespace App\Providers;

use App\Domains\Ai\Clients\HttpAiClient;
use App\Domains\Ai\Clients\OpenAiEmbeddingClient;
use App\Domains\Ai\Contracts\AiCompletionClient;
use App\Domains\Ai\Contracts\AiEmbeddingClient;
use App\Domains\Automation\Events\TicketAutomationTrigger;
use App\Domains\Csat\Observers\TicketCsatObserver;
use App\Domains\Notifications\Listeners\PublishAgentNotificationRealtime;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Automation\Listeners\RunAutomationRules;
use App\Domains\Integrations\Listeners\DispatchSlackNotifications;
use App\Domains\Integrations\Listeners\DispatchWebhooks;
use App\Domains\Integrations\Listeners\EnrichTicketFromCrm;
use App\Domains\Integrations\Listeners\SyncExternalIssues;
use App\Domains\Ai\Listeners\TriageTicketOnCreate;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
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
    }

    public function boot(): void
    {
        Event::listen(TicketAutomationTrigger::class, RunAutomationRules::class);
        Event::listen(TicketAutomationTrigger::class, DispatchWebhooks::class);
        Event::listen(TicketAutomationTrigger::class, DispatchSlackNotifications::class);
        Event::listen(TicketAutomationTrigger::class, SyncExternalIssues::class);
        Event::listen(TicketAutomationTrigger::class, EnrichTicketFromCrm::class);
        Event::listen(TicketAutomationTrigger::class, TriageTicketOnCreate::class);
        Event::listen(NotificationSent::class, PublishAgentNotificationRealtime::class);

        Ticket::observe(TicketCsatObserver::class);

        Vite::createAssetPathsUsing(
            fn (string $path, ?bool $secure = null) => '/'.ltrim($path, '/'),
        );

        if ($this->app->runningInConsole()) {
            $this->forceAppUrl();
        } elseif (in_array(request()->getHost(), config('tenancy.central_domains'), true)) {
            $this->forceAppUrl();
        }

        try {
            app(\App\Domains\Channels\Services\OutboundMailService::class)->applyGlobalConfig();
        } catch (\Throwable) {
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
}
