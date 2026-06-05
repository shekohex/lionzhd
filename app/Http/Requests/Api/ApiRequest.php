<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class ApiRequest extends FormRequest
{
    final public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = collect($validator->errors()->toArray())
            ->flatMap(fn (array $messages, string $field): array => array_map(
                static fn (string $message): array => [
                    'status' => '422',
                    'title' => 'Invalid Attribute',
                    'detail' => $message,
                    'source' => ['parameter' => $field],
                ],
                $messages,
            ))
            ->values()
            ->all();

        throw new HttpResponseException(response()->json([
            'errors' => $errors,
        ], 422)->header('Content-Type', 'application/vnd.api+json'));
    }
}
