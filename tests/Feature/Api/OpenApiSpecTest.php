<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('exports an openapi 3.1 spec with bearer auth and route specific settings schemas', function (): void {
    $path = storage_path('framework/testing-openapi.json');
    @unlink($path);

    Artisan::call('scramble:export', ['--path' => $path, '--silent' => true]);

    $spec = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    expect($spec['openapi'])->toBe('3.1.0')
        ->and($spec['components']['securitySchemes']['bearerAuth']['scheme'])->toBe('bearer')
        ->and($spec['paths']['/settings/xtreamcodes']['patch']['requestBody']['content']['application/json']['schema']['$ref'])->toBe('#/components/schemas/UpdateXtreamCodesSettingsRequest')
        ->and($spec['paths']['/settings/users/{user}/subtype']['patch']['requestBody']['content']['application/json']['schema']['$ref'])->toBe('#/components/schemas/UpdateSettingsUserSubtypeRequest')
        ->and($spec['paths']['/settings/schedules/bulk-apply']['patch']['requestBody']['content']['application/json']['schema']['$ref'])->toBe('#/components/schemas/BulkApplySchedulesRequest')
        ->and($spec['paths']['/settings/sync-categories']['patch']['requestBody']['content']['application/json']['schema']['$ref'])->toBe('#/components/schemas/SyncCategoriesSettingsRequest')
        ->and($spec['components']['schemas']['UpdateXtreamCodesSettingsRequest']['properties'])->toHaveKey('password')
        ->and($spec['components']['schemas']['UpdateXtreamCodesSettingsRequest']['properties'])->not->toHaveKey('secret')
        ->and($spec['components']['schemas']['UpdateAria2SettingsRequest']['properties'])->toHaveKey('secret')
        ->and($spec['components']['schemas']['UpdateAria2SettingsRequest']['properties'])->not->toHaveKey('password')
        ->and($spec['components']['schemas']['BulkApplySchedulesRequest']['properties'])->toHaveKeys(['preset', 'series_ids']);

    $contentTypes = collect($spec['paths'])
        ->flatMap(static fn (array $path): array => $path)
        ->flatMap(static fn (array $operation): array => array_keys($operation['responses']['200']['content'] ?? []))
        ->unique()
        ->values()
        ->all();

    expect($contentTypes)->toContain('application/vnd.api+json')
        ->and(openApiRefs($spec))->each->toStartWith('#/');

    foreach (openApiRefs($spec) as $ref) {
        $segments = array_map(static fn (string $segment): string => str_replace(['~1', '~0'], ['/', '~'], $segment), explode('/', mb_ltrim(mb_substr($ref, 2), '/')));
        $cursor = $spec;

        foreach ($segments as $segment) {
            expect($cursor)->toHaveKey($segment);
            $cursor = $cursor[$segment];
        }
    }
});

/** @return list<string> */
function openApiRefs(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }

    $refs = [];

    foreach ($value as $key => $item) {
        if ($key === '$ref' && is_string($item)) {
            $refs[] = $item;

            continue;
        }

        $refs = [...$refs, ...openApiRefs($item)];
    }

    return $refs;
}
