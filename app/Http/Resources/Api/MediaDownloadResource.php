<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Data\MediaDownloadRefData;
use App\Data\MediaDownloadStatusData;
use App\Models\MediaDownloadRef;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

final class MediaDownloadResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->download()->getKey();
    }

    public function toType(Request $request): string
    {
        return 'downloads';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $attributes = MediaDownloadRefData::from($this->download())->toArray();
        $modelAttributes = $this->download()->getAttributes();
        $status = $modelAttributes['api_download_status'] ?? null;

        if ($status instanceof MediaDownloadStatusData) {
            $attributes['downloadStatus'] = $status->toArray();
        }

        return $attributes;
    }

    private function download(): MediaDownloadRef
    {
        /** @var MediaDownloadRef $download */
        $download = $this->resource;

        return $download;
    }
}
