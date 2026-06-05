<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\User;
use App\Support\TokenAbilityRegistry;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class StoreTokenRequest extends ApiRequest
{
    public const array ALLOWED_ABILITIES = TokenAbilityRegistry::ALLOWED_ABILITIES;

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
        return app(TokenAbilityRegistry::class)->normalize($this->input('abilities', ['read']));
    }

    /**
     * @return list<string>
     */
    public function authorizedAbilities(User $user): array
    {
        $registry = app(TokenAbilityRegistry::class);
        $requested = $this->abilities();
        $forbidden = array_values(array_filter($requested, fn (string $ability): bool => ! $registry->canMintAbility($user, $ability)));

        if ($forbidden !== []) {
            throw ValidationException::withMessages([
                'abilities' => 'Requested token abilities cannot exceed the current token or account permissions.',
            ]);
        }

        return $requested;
    }
}
