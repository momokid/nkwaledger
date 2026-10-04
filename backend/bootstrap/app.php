<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\PreventBackHistory::class,
            \App\Http\Middleware\ExtendPasswordConfirmationOnActivity::class,
        ]);

        // trusts Railway's reverse proxy so Laravel correctly detects the original request was HTTPS
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'access' => \App\Http\Middleware\CheckPermission::class,
            'marketplace' => \App\Http\Middleware\EnsureMarketplaceEnabled::class,
            'otp.pending' => \App\Http\Middleware\EnsureOtpPending::class,
            'verified.phone' => \App\Http\Middleware\EnsurePhoneIsVerified::class,
            'activation.pending' => \App\Http\Middleware\EnsureActivationPending::class,
        ]);

        // the role and permission gates must run before route-model binding: otherwise a user
        // who may not open a page still gets 404 for a missing record and 403 for an existing
        // one, which lets them count records they have no business knowing about
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \Spatie\Permission\Middleware\RoleMiddleware::class,
        );
        $middleware->prependToPriorityList(
            before: \Spatie\Permission\Middleware\RoleMiddleware::class,
            prepend: \App\Http\Middleware\EnsureMarketplaceEnabled::class,
        );
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\CheckPermission::class,
        );

        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*')
                || ($request->expectsJson() && $request->is('sync/*', 'admin/sync-submissions/*')),
        );
    })->create();
