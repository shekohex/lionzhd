<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

final class StoreTokenRequest extends ApiRequest
{
    public const array ALLOWED_ABILITIES = [
        'read',
        'server-download',
        'download-operations',
        'monitoring:admin',
        'admin',
        'super-admin',
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['sometimes', 'array', 'list'],
            'abilities.*' => ['string', Rule::in(self::ALLOWED_ABILITIES)],
        ];
    }

    /**
     * @return list<string>
     */
    public function abilities(): array
    {
        $abilities = $this->input('abilities', ['read']);

        if (! is_array($abilities) || $abilities === []) {
            return ['read'];
        }

        return array_values(array_unique(array_map(static fn (mixed $ability): string => (string) $ability, $abilities)));
    }
}
