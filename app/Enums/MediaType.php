<?php

declare(strict_types=1);

namespace App\Enums;

enum MediaType: string
{
    case Movie = 'movie';
    case Series = 'series';

    public function isMovie(): bool
    {
        return $this === self::Movie;
    }

    public function isSeries(): bool
    {
        return $this === self::Series;
    }
}
