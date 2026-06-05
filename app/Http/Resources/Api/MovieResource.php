<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\VodStream;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

final class MovieResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->movie()->getKey();
    }

    public function toType(Request $request): string
    {
        return 'movies';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $data = $this->movie()->getData();

        return [
            'num' => $data->num,
            'name' => $data->name,
            'stream_type' => $data->stream_type,
            'stream_id' => $data->stream_id,
            'stream_icon' => $data->stream_icon,
            'rating' => $data->rating,
            'rating_5based' => $data->rating_5based,
            'added' => $data->added,
            'is_adult' => $data->is_adult,
            'category_id' => $data->category_id,
            'container_extension' => $data->container_extension,
            'custom_sid' => $data->custom_sid,
            'direct_source' => $data->direct_source,
            'created_at' => $data->created_at,
            'updated_at' => $data->updated_at,
            ...$this->vodInfoAttribute(),
        ];
    }

    /**
     * @return array{vod_info: array<string, mixed>}|array{}
     */
    private function vodInfoAttribute(): array
    {
        $attributes = $this->movie()->getAttributes();

        if (! array_key_exists('api_vod_info', $attributes)) {
            return [];
        }

        $vodInfo = $attributes['api_vod_info'];

        if (! is_array($vodInfo)) {
            return [];
        }

        /** @var array<string, mixed> $vodInfo */
        return ['vod_info' => $vodInfo];
    }

    private function movie(): VodStream
    {
        /** @var VodStream $movie */
        $movie = $this->resource;

        return $movie;
    }
}
