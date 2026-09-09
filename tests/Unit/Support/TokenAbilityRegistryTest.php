<?php

declare(strict_types=1);

use App\Support\TokenAbilityRegistry;

it('exposes every API token ability as an OAuth scope', function (): void {
    $registry = app(TokenAbilityRegistry::class);

    expect(array_keys($registry->scopeDescriptions()))
        ->toBe(TokenAbilityRegistry::ALLOWED_ABILITIES)
        ->and($registry->scopeDescriptions()['read'])
        ->toBe('Browse media, watchlists, discovery, profile, and token metadata.');
});
