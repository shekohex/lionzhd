<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Category extends Model
{
    public const string UNCATEGORIZED_VOD_PROVIDER_ID = '__uncategorized_vod__';

    public const string UNCATEGORIZED_SERIES_PROVIDER_ID = '__uncategorized_series__';

    protected $fillable = [
        'provider_id',
        'name',
        'in_vod',
        'in_series',
        'is_system',
        'vod_sync_order',
        'series_sync_order',
    ];

    protected function casts(): array
    {
        return [
            'in_vod' => 'boolean',
            'in_series' => 'boolean',
            'is_system' => 'boolean',
            'vod_sync_order' => 'integer',
            'series_sync_order' => 'integer',
        ];
    }
}
