<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TokenAbilityRegistry;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

final class TokensController extends Controller
{
    public function index(#[CurrentUser] User $user, TokenAbilityRegistry $abilities): Response
    {
        return Inertia::render('settings/tokens', [
            'tokens' => $user->tokens()
                ->select(['id', 'name', 'abilities', 'last_used_at', 'created_at'])
                ->latest('id')
                ->get()
                ->map(static fn (PersonalAccessToken $token): array => [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'last_used_at' => $token->last_used_at,
                    'created_at' => $token->created_at,
                ]),
            'abilityOptions' => $abilities->optionsFor($user, respectCurrentToken: false),
        ]);
    }

    public function store(Request $request, #[CurrentUser] User $user, TokenAbilityRegistry $abilities): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['sometimes', 'array', 'list'],
            'abilities.*' => ['string', Rule::in(TokenAbilityRegistry::ALLOWED_ABILITIES)],
        ]);

        $authorizedAbilities = $this->authorizedAbilities($user, $abilities, $validated['abilities'] ?? ['read']);
        $newAccessToken = $user->createToken((string) $validated['name'], $authorizedAbilities);

        return to_route('tokens.index')->with('api_token', $newAccessToken->plainTextToken);
    }

    public function destroy(#[CurrentUser] User $user, PersonalAccessToken $token): RedirectResponse
    {
        abort_unless($token->tokenable_type === $user->getMorphClass() && (int) $token->tokenable_id === (int) $user->getKey(), 404);

        $token->delete();

        return to_route('tokens.index')->with('success', 'Token revoked.');
    }

    /**
     * @return list<string>
     */
    private function authorizedAbilities(User $user, TokenAbilityRegistry $registry, mixed $abilities): array
    {
        $requested = $registry->normalize($abilities);

        foreach ($requested as $ability) {
            abort_if(! $registry->canMintAbility($user, $ability, respectCurrentToken: false), 403);
        }

        return $requested;
    }
}
