<?php

use App\Exceptions\InvalidCredentialsException;
use App\Http\Responses\ApiResponse;
use App\Services\DiscordErrorReporter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Returning null makes the Authenticate middleware throw AuthenticationException
        // instead of building a redirect to the "login" route, which does not exist here
        // and would surface as a 500 before the exception handler ever sees it.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
                ? null
                : route('login'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A wrong password is a normal event, not a fault. Laravel already keeps
        // validation, auth and 404s out of reporting; this one is ours, so without
        // it every mistyped password would ring the alert channel.
        $exceptions->dontReport(InvalidCredentialsException::class);

        // Alongside the log, not instead of it.
        $exceptions->report(function (Throwable $e): void {
            app(DiscordErrorReporter::class)->report($e);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Runs ahead of the framework's own handling, which would otherwise redirect an
        // unauthenticated API call to the non-existent "login" route and return 500.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::fromException($e);
        });
    })->create();
