<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class SeriesData extends Data
{
    public int $num;

    public string $name;

    public int $series_id;

    public string $cover;

    public string $plot;

    public string $cast;

    public string $director;

    public string $genre;

    /**
     * @var ArrayObject<string>
     */
    public ArrayObject $backdrop_path;

    public string $releaseDate;

    public CarbonImmutable $last_modified;

    public float $rating;

    public float $rating_5based;

    public CarbonImmutable $created_at;

    public CarbonImmutable $updated_at;
}
