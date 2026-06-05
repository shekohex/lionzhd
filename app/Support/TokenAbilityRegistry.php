<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class TokenAbilityRegistry
{
    public const array ALLOWED_ABILITIES = [
        'read',
        'server-download',
        'download-operations',
        'monitoring:admin',
        'admin',
        'super-admin',
    ];

    /** @return list<array{value: string, label: string, description: string}> */
    public function optionsFor(User $user, bool $respectCurrentToken = true): array
    {
        return array_values(array_filter(array_map(fn (string $ability): ?array => $this->canMintAbility($user, $ability, $respectCurrentToken) ? [
            'value' => $ability,
            'label' => $this->labels()[$ability],
            'description' => $this->descriptions()[$ability],
        ] : null, self::ALLOWED_ABILITIES)));
    }

    public function canMintAbility(User $user, string $ability, bool $respectCurrentToken = true): bool
    {
        $currentToken = $user->currentAccessToken();

        if ($respectCurrentToken && $currentToken !== null && ! $currentToken->can($ability)) {
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

    /**
     * @return list<string>
     */
    public function normalize(mixed $abilities): array
    {
        if (! is_array($abilities) || $abilities === []) {
            return ['read'];
        }

        return array_values(array_unique(array_map(static fn (mixed $ability): string => (string) $ability, $abilities)));
    }

    /** @return array<string, string> */
    private function labels(): array
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
    private function descriptions(): array
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
