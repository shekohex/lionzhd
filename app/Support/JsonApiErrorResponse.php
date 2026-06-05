<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
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

        if (! is_array($decoded) || ! array_key_exists('errors', $decoded) || ! array_is_list($decoded['errors'])) {
            return false;
        }

        foreach ($decoded['errors'] as $error) {
            if (! is_array($error) || ! isset($error['status'], $error['title'], $error['detail'])) {
                return false;
            }
        }

        return true;
    }

    public static function fromValidationException(ValidationException $exception): JsonResponse
    {
        $errors = collect($exception->errors())
            ->flatMap(fn (array $messages, string $field): array => array_map(
                static fn (string $message): array => [
                    'status' => '422',
                    'title' => Response::$statusTexts[422],
                    'detail' => $message,
                    'source' => ['parameter' => $field],
                ],
                $messages,
            ))
            ->values()
            ->all();

        if ($errors === []) {
            return self::make(422, $exception->getMessage());
        }

        return response()->json(['errors' => $errors], 422)->header('Content-Type', 'application/vnd.api+json');
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
