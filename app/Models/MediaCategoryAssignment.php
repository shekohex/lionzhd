<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class MediaCategoryAssignment extends Model
{
    protected $fillable = [
        'media_type',
        'media_provider_id',
        'category_provider_id',
        'source_order',
    ];

    protected function casts(): array
    {
        return [
            'source_order' => 'integer',
        ];
    }
}
