<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

final class CategoryResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return $this->category()->provider_id;
    }

    public function toType(Request $request): string
    {
        return 'categories';
    }

    /**
     * @return array{name: string, in_vod: bool, in_series: bool, is_system: bool, vod_sync_order: int|null, series_sync_order: int|null}
     */
    public function toAttributes(Request $request): array
    {
        $category = $this->category();

        return [
            'name' => $category->name,
            'in_vod' => (bool) $category->in_vod,
            'in_series' => (bool) $category->in_series,
            'is_system' => (bool) $category->is_system,
            'vod_sync_order' => $category->vod_sync_order,
            'series_sync_order' => $category->series_sync_order,
        ];
    }

    private function category(): Category
    {
        /** @var Category $category */
        $category = $this->resource;

        return $category;
    }
}
