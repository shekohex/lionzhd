<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\SearchSortby;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use UnexpectedValueException;

/**
 * @implements CastsAttributes<SearchSortby,string>
 */
final class AsSearchSortBy implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): SearchSortby
    {
        $sortBy = SearchSortby::tryFrom($value);
        throw_if($sortBy === null, new UnexpectedValueException("Invalid search sort by value: {$value}"));

        return $sortBy;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return $value;
    }
}
