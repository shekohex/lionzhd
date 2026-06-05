<?php

declare(strict_types=1);

use App\Http\Middleware\AcceptJsonApi;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance']);
        $middleware->statefulApi();
        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
        $middleware->alias([
            'AcceptJsonApi' => AcceptJsonApi::class,
        ]);

        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_TRAEFIK |
            Request::HEADER_X_FORWARDED_AWS_ELB
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        $exceptions->respond(function (SymfonyResponse $response, Throwable $exception, Request $request): SymfonyResponse {
            if ($request->is('api/v1') || $request->is('api/v1/*')) {
                $response->headers->set('Content-Type', 'application/vnd.api+json');

                return $response;
            }

            if ($response->getStatusCode() === 404 && ! $request->expectsJson()) {
                return Inertia::render('errors/not-found', [
                    'message' => 'The page you requested could not be found.',
                ])->toResponse($request)->setStatusCode(404);
            }

            if ($response->getStatusCode() !== 403 || ! $request->header('X-Inertia')) {
                return $response;
            }

            $reason = 'You are not authorized to perform this action.';

            if ($exception instanceof AuthorizationException && $exception->getMessage() !== '') {
                $reason = $exception->getMessage();
            }

            if ($exception instanceof AccessDeniedHttpException && $exception->getPrevious() instanceof AuthorizationException) {
                $previousMessage = $exception->getPrevious()->getMessage();

                if ($previousMessage !== '') {
                    $reason = $previousMessage;
                }
            }

            return Inertia::render('errors/forbidden', [
                'reason' => $reason,
                'message' => $reason,
            ])->toResponse($request)->setStatusCode(403);
        });
    })->create();
