<?php

namespace App\Providers;

use App\Domains\Ai\Clients\HttpAiClient;
use App\Domains\Ai\Contracts\AiCompletionClient;
use App\Domains\Automation\Events\TicketAutomationTrigger;
use App\Domains\Csat\Observers\TicketCsatObserver;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Automation\Listeners\RunAutomationRules;
use App\Domains\Integrations\Listeners\DispatchSlackNotifications;
use App\Domains\Integrations\Listeners\DispatchWebhooks;
use App\Domains\Integrations\Listeners\SyncExternalIssues;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Domains\Brands\Support\BrandContext::class);
        $this->app->bind(AiCompletionClient::class, HttpAiClient::class);
    }

    public function boot(): void
    {
        Event::listen(TicketAutomationTrigger::class, RunAutomationRules::class);
        Event::listen(TicketAutomationTrigger::class, DispatchWebhooks::class);
        Event::listen(TicketAutomationTrigger::class, DispatchSlackNotifications::class);
        Event::listen(TicketAutomationTrigger::class, SyncExternalIssues::class);

        Ticket::observe(TicketCsatObserver::class);

        if ($appUrl = config('app.url')) {
            URL::forceRootUrl($appUrl);

            if ($scheme = parse_url($appUrl, PHP_URL_SCHEME)) {
                URL::forceScheme($scheme);
            }
        }

        try {
            app(\App\Domains\Channels\Services\OutboundMailService::class)->applyGlobalConfig();
        } catch (\Throwable) {
        }
    }
}
