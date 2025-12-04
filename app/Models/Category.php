<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Category extends Model
{
    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'parent_id',
        'type',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'parent_id' => 'integer',
        ];
    }

    /**
     * Get the series for the category.
     *
     * @return HasMany<Series>
     */
    public function series(): HasMany
    {
        return $this->hasMany(Series::class, 'category_id', 'id');
    }

    /**
     * Get the VOD streams for the category.
     *
     * @return HasMany<VodStream>
     */
    public function vodStreams(): HasMany
    {
        return $this->hasMany(VodStream::class, 'category_id', 'id');
    }
}
