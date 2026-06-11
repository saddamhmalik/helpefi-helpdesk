<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

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
            'workspace.setup' => \App\Http\Middleware\EnsureWorkspaceSetup::class,
            'subscription.active' => \App\Http\Middleware\EnsureActiveSubscription::class,
            'central.admin' => \App\Http\Middleware\EnsureCentralAdmin::class,
            'platform.permission' => \App\Http\Middleware\EnsurePlatformPermission::class,
            'tenant.not_blocked' => \App\Http\Middleware\EnsureTenantNotBlocked::class,
            'tenant.custom_domain_redirect' => \App\Http\Middleware\RedirectToCustomDomain::class,
            'portal.locale' => \App\Http\Middleware\ResolvePortalLocale::class,
        ]);

        $middleware->web(prepend: [
            \App\Http\Middleware\ConfigureApplicationSession::class,
            \App\Http\Middleware\RedirectCentralWww::class,
            \App\Http\Middleware\InitializeTenancyWhenNotCentral::class,
            \App\Http\Middleware\RewriteUnscopedPortalUrl::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\ExpireLegacySessionCookies::class,
            \App\Http\Middleware\ForgetTenantWebAuthOnCentral::class,
            \App\Http\Middleware\SetUserLocale::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if ($request->header('X-Inertia')) {
                return Inertia::location($request->fullUrl());
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Page expired.'], 419);
            }

            return null;
        });

        $exceptions->respond(function (Response $response, \Throwable $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();

            if (! in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                return $response;
            }

            $page = match (true) {
                $status === 404 => 'Error/NotFound',
                in_array($status, [500, 503], true) => 'Error/ServerError',
                default => 'Error/Generic',
            };

            return Inertia::render($page, [
                'status' => $status,
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
