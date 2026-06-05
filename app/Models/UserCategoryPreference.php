<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperUserCategoryPreference
 */
final class UserCategoryPreference extends Model
{
    protected $fillable = [
        'user_id',
        'media_type',
        'category_provider_id',
        'pin_rank',
        'sort_order',
        'is_hidden',
        'is_ignored',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'media_type' => MediaType::class,
            'pin_rank' => 'integer',
            'sort_order' => 'integer',
            'is_hidden' => 'boolean',
            'is_ignored' => 'boolean',
        ];
    }
}
