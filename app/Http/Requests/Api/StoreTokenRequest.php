<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    /**
     * @return list<string>
     */
    public function authorizedAbilities(User $user): array
    {
        $requested = $this->abilities();
        $forbidden = array_values(array_filter($requested, fn (string $ability): bool => ! $this->canMintAbility($user, $ability)));

        if ($forbidden !== []) {
            throw ValidationException::withMessages([
                'abilities' => 'Requested token abilities cannot exceed the current token or account permissions.',
            ]);
        }

        return $requested;
    }

    private function canMintAbility(User $user, string $ability): bool
    {
        $currentToken = $user->currentAccessToken();

        if ($currentToken !== null && ! $currentToken->can($ability)) {
            return false;
        }

        return match ($ability) {
            'read' => true,
            'server-download' => Gate::forUser($user)->allows('server-download'),
            'download-operations' => $user->canPerformDownloadOperations(),
            'monitoring:admin' => Gate::forUser($user)->allows('auto-download-schedules'),
            'admin' => Gate::forUser($user)->allows('admin'),
            'super-admin' => Gate::forUser($user)->allows('super-admin'),
            default => false,
        };
    }
}
