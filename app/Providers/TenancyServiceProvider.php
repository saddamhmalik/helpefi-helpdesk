<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use App\Domains\Tenancy\Jobs\CreateTenantDatabaseJob;
use App\Domains\Tenancy\Jobs\EnsurePlatformDomainJob;
use App\Domains\Tenancy\Jobs\FinalizeTenantProvisioningJob;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\DatabaseConfig;
use Stancl\Tenancy\Middleware;

class TenancyServiceProvider extends ServiceProvider
{
    // By default, no namespace is used to support the callable array syntax.
    public static string $controllerNamespace = '';

    public function events()
    {
        return [
            // Tenant events
            Events\CreatingTenant::class => [],
            Events\TenantCreated::class => [
                JobPipeline::make([
                    EnsurePlatformDomainJob::class,
                    CreateTenantDatabaseJob::class,
                    Jobs\MigrateDatabase::class,
                    FinalizeTenantProvisioningJob::class,
                ])->send(function (Events\TenantCreated $event) {
                    return $event->tenant;
                })->shouldBeQueued(false), // `false` by default, but you probably want to make this `true` for production.
            ],
            Events\SavingTenant::class => [],
            Events\TenantSaved::class => [],
            Events\UpdatingTenant::class => [],
            Events\TenantUpdated::class => [],
            Events\DeletingTenant::class => [],
            Events\TenantDeleted::class => [
                JobPipeline::make([
                    Jobs\DeleteDatabase::class,
                ])->send(function (Events\TenantDeleted $event) {
                    return $event->tenant;
                })->shouldBeQueued(false), // `false` by default, but you probably want to make this `true` for production.
            ],

            // Domain events
            Events\CreatingDomain::class => [],
            Events\DomainCreated::class => [],
            Events\SavingDomain::class => [],
            Events\DomainSaved::class => [],
            Events\UpdatingDomain::class => [],
            Events\DomainUpdated::class => [],
            Events\DeletingDomain::class => [],
            Events\DomainDeleted::class => [],

            // Database events
            Events\DatabaseCreated::class => [],
            Events\DatabaseMigrated::class => [],
            Events\DatabaseSeeded::class => [],
            Events\DatabaseRolledBack::class => [],
            Events\DatabaseDeleted::class => [],

            // Tenancy events
            Events\InitializingTenancy::class => [],
            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],

            Events\EndingTenancy::class => [],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],

            Events\BootstrappingTenancy::class => [
                \App\Domains\Tenancy\Listeners\BootstrapTenantUrl::class.'@handleBootstrappingTenancy',
            ],
            Events\TenancyBootstrapped::class => [
                \App\Domains\Tenancy\Listeners\BootstrapTenantUrl::class.'@handleTenancyBootstrapped',
            ],
            Events\RevertingToCentralContext::class => [],
            Events\RevertedToCentralContext::class => [
                \App\Domains\Tenancy\Listeners\BootstrapTenantUrl::class.'@handleRevertedToCentralContext',
            ],

            // Resource syncing
            Events\SyncedResourceSaved::class => [
                Listeners\UpdateSyncedResource::class,
            ],

            // Fired only when a synced resource is changed in a different DB than the origin DB (to avoid infinite loops)
            Events\SyncedResourceChangedInForeignDatabase::class => [],
        ];
    }

    public function register()
    {
        //
    }

    public function boot()
    {
        DatabaseConfig::generateDatabaseNamesUsing(
            fn ($tenant) => config('tenancy.database.prefix').$tenant->slug
        );

        $this->bootEvents();
        $this->mapRoutes();

        $this->makeTenancyMiddlewareHighestPriority();
    }

    protected function bootEvents()
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }

    protected function mapRoutes()
    {
        $this->app->booted(function () {
            $central = (string) config('tenancy.central_app_domain');

            if ($central !== '') {
                Route::domain('www.'.$central)
                    ->middleware('web')
                    ->any('{path?}', function (?string $path = null) use ($central) {
                        $uri = request()->getRequestUri();

                        return redirect()->away(request()->getScheme().'://'.$central.$uri, 301);
                    })
                    ->where('path', '.*');
            }

            Route::domain($central)
                ->middleware('web')
                ->group(base_path('routes/web.php'));

            if (file_exists(base_path('routes/tenant.php'))) {
                Route::namespace(static::$controllerNamespace)
                    ->group(base_path('routes/tenant.php'));
            }

            if (file_exists(base_path('routes/tenant-api.php'))) {
                Route::prefix('api')
                    ->middleware('api')
                    ->namespace(static::$controllerNamespace)
                    ->group(base_path('routes/tenant-api.php'));
            }
        });
    }

    protected function makeTenancyMiddlewareHighestPriority()
    {
        $tenancyMiddleware = [
            // Even higher priority than the initialization middleware
            Middleware\PreventAccessFromCentralDomains::class,

            Middleware\InitializeTenancyByDomain::class,
            Middleware\InitializeTenancyBySubdomain::class,
            Middleware\InitializeTenancyByDomainOrSubdomain::class,
            Middleware\InitializeTenancyByPath::class,
            Middleware\InitializeTenancyByRequestData::class,
            \App\Http\Middleware\InitializeTenancyForPublicApi::class,
        ];

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $this->app[\Illuminate\Contracts\Http\Kernel::class]->prependToMiddlewarePriority($middleware);
        }
    }
}
