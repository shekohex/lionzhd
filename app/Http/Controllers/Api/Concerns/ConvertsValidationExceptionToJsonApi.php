<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use App\Support\JsonApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

trait ConvertsValidationExceptionToJsonApi
{
    private function validationError(ValidationException $exception, int $status = 422): JsonResponse
    {
        $errors = $exception->errors();
        $parameter = array_key_first($errors) ?? 'series';
        $detail = (string) ($errors[$parameter][0] ?? $exception->getMessage());

        return JsonApiErrorResponse::make($status, $detail, sourceParameter: (string) $parameter);
    }
}
