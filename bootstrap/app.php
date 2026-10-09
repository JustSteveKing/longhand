<?php

use App\Http\Api\JsonApi\ApiError;
use App\Http\Api\JsonApi\ErrorRenderer;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Longhand\Shared\Actions\ActionRefused;
use Longhand\Shared\Errors\DomainError;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Everything under /v1 is answered as a JSON:API error document (RFC 0002).
        $exceptions->render(fn (Throwable $error, Request $request) => $request->is('v1', 'v1/*')
            ? ErrorRenderer::render($error, $request)
            : null);

        // Refusals and domain errors are the client's, not ours to report.
        $exceptions->dontReport([ActionRefused::class, DomainError::class, ApiError::class]);

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->expectsJson());
    })->create();
