<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class JsonApiErrorResponse
{
    public static function make(int $status, string $detail, ?string $title = null): JsonResponse
    {
        return response()->json([
            'errors' => [[
                'status' => (string) $status,
                'title' => $title ?? Response::$statusTexts[$status] ?? 'Error',
                'detail' => $detail,
            ]],
        ], $status)->header('Content-Type', 'application/vnd.api+json');
    }

    public static function alreadyJsonApi(Response $response): bool
    {
        $decoded = json_decode((string) $response->getContent(), true);

        return is_array($decoded) && array_key_exists('errors', $decoded);
    }

    public static function safeDetail(int $status, Throwable $exception): string
    {
        $fallback = Response::$statusTexts[$status] ?? 'Error';

        if ($status >= 400 && ! config('app.debug')) {
            return $fallback;
        }

        return $exception->getMessage() !== '' ? $exception->getMessage() : $fallback;
    }
}
