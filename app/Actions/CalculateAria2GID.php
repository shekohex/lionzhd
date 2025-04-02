<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Http\Integrations\LionzTv\Responses\VodInformation;
use InvalidArgumentException;

/**
 * @method static string run( class-string<VodInformation|Episode> $kind, int $download_id)
 */
final class CalculateAria2GID
{
    use AsAction;

    /**
     * Execute the action.
     *
     * @param  class-string<VodInformation|Episode>  $kind
     */
    public function __invoke(
        string $kind,
        int $download_id,
    ): string {
        $modelPath = match ($kind) {
            VodInformation::class => 'movie',
            Episode::class => 'series',
            default => throw new InvalidArgumentException('Invalid kind provided.'),
        };
        $data = "{$modelPath}/{$download_id}";
        $hash = hash('xxh3', $data);

        return $hash;
    }
}
