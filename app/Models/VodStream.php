<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class VodStream extends Model
{
    use Searchable;

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'vod_streams';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'num';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'num',
        'name',
        'stream_type',
        'stream_id',
        'stream_icon',
        'rating',
        'rating_5based',
        'added',
        'is_adult',
        'category_id',
        'container_extension',
        'custom_sid',
        'direct_source',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'num' => 'integer',
        'stream_id' => 'integer',
        'rating_5based' => 'decimal:1',
        'is_adult' => 'boolean',
        'added' => 'datetime',
    ];

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
