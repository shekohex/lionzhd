<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class VodStreamData extends Data
{
    public int $num;

    public string $name;

    public string $stream_type;

    public int $stream_id;

    public string $stream_icon;

    public string $rating;

    public float $rating_5based;

    public CarbonImmutable $added;

    public bool $is_adult;

    public ?int $category_id;

    public string $container_extension;

    public ?string $custom_sid;

    public ?string $direct_source;

    public CarbonImmutable $created_at;

    public CarbonImmutable $updated_at;
}
