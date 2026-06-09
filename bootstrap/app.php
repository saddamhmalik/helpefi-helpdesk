<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'agent' => \App\Http\Middleware\EnsureUserIsAgent::class,
            'audit.view' => \App\Http\Middleware\EnsureCanViewAudit::class,
            'customer' => \App\Http\Middleware\EnsureUserIsCustomer::class,
            'api.token' => \App\Http\Middleware\AuthenticateApiToken::class,
            'two-factor' => \App\Http\Middleware\EnsureTwoFactorVerified::class,
            'chat.widget.cors' => \App\Http\Middleware\ChatWidgetCors::class,
            'tenancy.public-api' => \App\Http\Middleware\InitializeTenancyForPublicApi::class,
            'brand' => \App\Http\Middleware\ResolveBrand::class,
        ]);

        $middleware->web(prepend: [
            \App\Http\Middleware\InitializeTenancyWhenNotCentral::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
