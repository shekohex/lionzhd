<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTokenRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

final class TokensController extends Controller
{
    public function index(#[CurrentUser] User $user): Response
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
            'abilityOptions' => $this->abilityOptions($user),
        ]);
    }

    public function store(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['sometimes', 'array', 'list'],
            'abilities.*' => ['string', Rule::in(StoreTokenRequest::ALLOWED_ABILITIES)],
        ]);

        $abilities = $this->authorizedAbilities($user, $validated['abilities'] ?? ['read']);
        $newAccessToken = $user->createToken((string) $validated['name'], $abilities);

        return to_route('tokens.index')->with('api_token', $newAccessToken->plainTextToken);
    }

    public function destroy(#[CurrentUser] User $user, PersonalAccessToken $token): RedirectResponse
    {
        abort_unless($token->tokenable_type === $user->getMorphClass() && (int) $token->tokenable_id === (int) $user->getKey(), 404);

        $token->delete();

        return to_route('tokens.index')->with('success', 'Token revoked.');
    }

    /** @return list<array{value: string, label: string, description: string}> */
    private function abilityOptions(User $user): array
    {
        return array_values(array_filter(array_map(fn (string $ability): ?array => $this->canMintAbility($user, $ability) ? [
            'value' => $ability,
            'label' => $this->abilityLabels()[$ability],
            'description' => $this->abilityDescriptions()[$ability],
        ] : null, StoreTokenRequest::ALLOWED_ABILITIES)));
    }

    /**
     * @return list<string>
     */
    private function authorizedAbilities(User $user, mixed $abilities): array
    {
        $requested = is_array($abilities) && $abilities !== []
            ? array_values(array_unique(array_map(static fn (mixed $ability): string => (string) $ability, $abilities)))
            : ['read'];

        foreach ($requested as $ability) {
            abort_if(! $this->canMintAbility($user, $ability), 403);
        }

        return $requested;
    }

    private function canMintAbility(User $user, string $ability): bool
    {
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

    /** @return array<string, string> */
    private function abilityLabels(): array
    {
        return [
            'read' => 'Read API data',
            'server-download' => 'Create downloads',
            'download-operations' => 'Manage downloads',
            'monitoring:admin' => 'Manage monitoring',
            'admin' => 'Admin settings',
            'super-admin' => 'Super admin actions',
        ];
    }

    /** @return array<string, string> */
    private function abilityDescriptions(): array
    {
        return [
            'read' => 'Browse media, watchlists, discovery, profile, and token metadata.',
            'server-download' => 'Start movie and series episode downloads.',
            'download-operations' => 'Pause, resume, remove, or cancel active downloads.',
            'monitoring:admin' => 'Create, update, run, and disable series monitoring.',
            'admin' => 'Read and update administrative settings.',
            'super-admin' => 'Perform super-admin-only account changes.',
        ];
    }
}
