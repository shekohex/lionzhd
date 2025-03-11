<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\MediaType;
use App\Enums\SearchSortby;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

final class SearchMediaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'media_type' => ['nullable', 'string', Rule::enum(MediaType::class)],
            'sort_by' => ['nullable', 'string', Rule::enum(SearchSortby::class)],
            'lightweight' => ['nullable', 'boolean'],
        ];
    }
}
