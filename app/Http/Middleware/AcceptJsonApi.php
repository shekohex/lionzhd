<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AcceptJsonApi
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('Accept') === null) {
            $request->headers->set('Accept', 'application/vnd.api+json');
        }

        if ($request->isMethodCacheable() === false && $request->getContent() !== '' && $request->header('Content-Type') === null) {
            $request->headers->set('Content-Type', 'application/vnd.api+json');
        }

        $response = $next($request);

        if ($response->headers->get('Content-Type') === null || str_starts_with((string) $response->headers->get('Content-Type'), 'application/json')) {
            $response->headers->set('Content-Type', 'application/vnd.api+json');
        }

        return $response;
    }
}
