<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\CategoryData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\LaravelData\WithData;

/**
 * @mixin IdeHelperCategory
 */
final class Category extends Model
{
    use WithData;

    protected $dataClass = CategoryData::class;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'category_name',
        'parent_id',
        'type',
    ];

    /**
     * Get the movies for the category.
     *
     * @return HasMany<VodStream,$this>
     */
    public function movies(): HasMany
    {
        return $this->hasMany(VodStream::class, 'category_id', 'category_id');
    }

    /**
     * Get the series for the category.
     *
     * @return HasMany<Series,$this>
     */
    public function series(): HasMany
    {
        return $this->hasMany(Series::class, 'category_id', 'category_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'parent_id' => 'integer',
        ];
    }
}
