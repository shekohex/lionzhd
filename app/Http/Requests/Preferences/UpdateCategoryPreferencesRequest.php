<?php

declare(strict_types=1);

namespace App\Http\Requests\Preferences;

use App\Enums\MediaType;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateCategoryPreferencesRequest extends FormRequest
{
    private const string ALL_CATEGORIES_ID = 'all-categories';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pinned_ids' => ['present', 'array', 'max:5'],
            'pinned_ids.*' => ['string', 'distinct:strict'],
            'visible_ids' => ['present', 'array'],
            'visible_ids.*' => ['string', 'distinct:strict'],
            'hidden_ids' => ['present', 'array'],
            'hidden_ids.*' => ['string', 'distinct:strict'],
            'ignored_ids' => ['present', 'array'],
            'ignored_ids.*' => ['string', 'distinct:strict'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pinned_ids' => $this->input('pinned_ids', []),
            'visible_ids' => $this->input('visible_ids', []),
            'hidden_ids' => $this->input('hidden_ids', []),
            'ignored_ids' => $this->input('ignored_ids', []),
        ]);
    }

    public function messages(): array
    {
        return [
            'pinned_ids.max' => 'You can pin up to 5 categories per media type.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $mediaType = $this->resolvedMediaType();

            if (! $mediaType instanceof MediaType) {
                return;
            }

            $visibleIds = $this->normalizedList('visible_ids');
            $hiddenIds = $this->normalizedList('hidden_ids');
            $ignoredIds = $this->normalizedList('ignored_ids');
            $pinnedIds = $this->normalizedList('pinned_ids');
            $editableIds = $this->editableCategoryIds($mediaType);
            $disallowedIds = [
                self::ALL_CATEGORIES_ID,
                Category::UNCATEGORIZED_VOD_PROVIDER_ID,
                Category::UNCATEGORIZED_SERIES_PROVIDER_ID,
            ];

            $this->ensureValidIds($validator, 'visible_ids', $visibleIds, $editableIds, $disallowedIds);
            $this->ensureValidIds($validator, 'hidden_ids', $hiddenIds, $editableIds, $disallowedIds);
            $this->ensureValidIds($validator, 'ignored_ids', $ignoredIds, $editableIds, $disallowedIds);
            $this->ensureValidIds($validator, 'pinned_ids', $pinnedIds, $editableIds, $disallowedIds);

            $this->ensureNoOverlap($validator, 'visible_ids', $visibleIds, 'hidden_ids', $hiddenIds, 'A category cannot be both visible and hidden in the same snapshot.');
            $this->ensureNoOverlap($validator, 'visible_ids', $visibleIds, 'ignored_ids', $ignoredIds, 'A category cannot be both visible and ignored in the same snapshot.');
            $this->ensureNoOverlap($validator, 'hidden_ids', $hiddenIds, 'ignored_ids', $ignoredIds, 'A category cannot be both hidden and ignored in the same snapshot.');

            $unpinnedVisibleIds = array_values(array_diff($pinnedIds, $visibleIds));

            if ($unpinnedVisibleIds !== []) {
                $validator->errors()->add('pinned_ids', 'Pinned categories must also be present in the visible list.');
            }
        });
    }

    private function resolvedMediaType(): ?MediaType
    {
        $mediaType = $this->route('mediaType');

        if ($mediaType instanceof MediaType) {
            return $mediaType;
        }

        if (! is_string($mediaType)) {
            return null;
        }

        return MediaType::tryFrom($mediaType);
    }

    private function normalizedList(string $key): array
    {
        $values = $this->input($key, []);

        if (! is_array($values)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $value): string => trim((string) $value), $values));
    }

    private function editableCategoryIds(MediaType $mediaType): array
    {
        $scopeColumn = $mediaType->isMovie() ? 'in_vod' : 'in_series';

        return Category::query()
            ->where($scopeColumn, true)
            ->pluck('provider_id')
            ->reject(static fn (string $providerId): bool => in_array($providerId, [
                Category::UNCATEGORIZED_VOD_PROVIDER_ID,
                Category::UNCATEGORIZED_SERIES_PROVIDER_ID,
            ], true))
            ->values()
            ->all();
    }

    private function ensureValidIds(Validator $validator, string $field, array $ids, array $editableIds, array $disallowedIds): void
    {
        if (array_values(array_intersect($ids, $disallowedIds)) !== []) {
            $validator->errors()->add($field, 'Fixed category rows cannot be customized.');
        }

        if (array_values(array_diff($ids, $editableIds, $disallowedIds)) !== []) {
            $validator->errors()->add($field, 'Every category id must belong to the requested media type.');
        }
    }

    private function ensureNoOverlap(Validator $validator, string $leftField, array $leftIds, string $rightField, array $rightIds, string $message): void
    {
        if (array_values(array_intersect($leftIds, $rightIds)) === []) {
            return;
        }

        $validator->errors()->add($leftField, $message);
        $validator->errors()->add($rightField, $message);
    }
}
