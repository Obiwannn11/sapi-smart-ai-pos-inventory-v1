<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Inertia middleware
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // Alias middleware
        $middleware->alias([
            'tenant' => \App\Http\Middleware\EnsureTenant::class,
            'tenant.api' => \App\Http\Middleware\EnsureTenantApi::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render a branded Inertia error page for 400/500 responses in
        // production. Admins (owners) and cashier staff get their own page so
        // the primary action returns them to the right place.
        $exceptions->respond(function (Response $response, \Throwable $exception, Request $request): Response {
            $status = $response->getStatusCode();

            if (! app()->environment(['local', 'testing']) && in_array($status, [400, 500], true)) {
                $component = $request->user()?->isOwner() ? 'Errors/Admin' : 'Errors/User';

                return Inertia::render($component, ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
