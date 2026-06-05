<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\VodStream;
use Carbon\CarbonImmutable;
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
     * @return array{
     *     num: int,
     *     name: string,
     *     stream_type: string,
     *     stream_id: int,
     *     stream_icon: string,
     *     rating: string,
     *     rating_5based: float,
     *     added: CarbonImmutable,
     *     is_adult: bool,
     *     category_id: string|null,
     *     container_extension: string,
     *     custom_sid: string|null,
     *     direct_source: string|null,
     *     created_at: CarbonImmutable,
     *     updated_at: CarbonImmutable
     * }
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
        ];
    }

    private function movie(): VodStream
    {
        /** @var VodStream $movie */
        $movie = $this->resource;

        return $movie;
    }
}
