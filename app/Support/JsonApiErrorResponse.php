<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class JsonApiErrorResponse
{
    public static function make(int $status, string $detail, ?string $title = null, ?string $sourceParameter = null): JsonResponse
    {
        $error = [
            'status' => (string) $status,
            'title' => $title ?? Response::$statusTexts[$status] ?? 'Error',
            'detail' => $detail,
        ];

        if ($sourceParameter !== null) {
            $error['source'] = ['parameter' => $sourceParameter];
        }

        return response()->json(['errors' => [$error]], $status)->header('Content-Type', 'application/vnd.api+json');
    }

    public static function alreadyJsonApi(Response $response): bool
    {
        $decoded = json_decode((string) $response->getContent(), true);

        return is_array($decoded) && array_key_exists('errors', $decoded);
    }

    public static function safeDetail(int $status, Throwable $exception): string
    {
        $fallback = Response::$statusTexts[$status] ?? 'Error';

        if ($status >= 500 && ! config('app.debug')) {
            return $fallback;
        }

        if ($status === 404 && ! config('app.debug') && self::isUnsafeNotFoundDetail($exception)) {
            return $fallback;
        }

        return $exception->getMessage() !== '' ? $exception->getMessage() : $fallback;
    }

    private static function isUnsafeNotFoundDetail(Throwable $exception): bool
    {
        if ($exception instanceof ModelNotFoundException || $exception->getPrevious() instanceof ModelNotFoundException) {
            return true;
        }

        return str_contains($exception->getMessage(), 'No query results for model');
    }
}
