<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Support\JsonApiErrorResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class ApiRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    final public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(JsonApiErrorResponse::fromValidator($validator));
    }
}
