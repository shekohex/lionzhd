<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Http\Integrations\LionzTv\Responses\Episode;
use Illuminate\Support\Collection;

/**
 * @method static Collection<int, string> run(array<int, Episode> $episodes)
 */
final readonly class BatchCreateSignedDirectLinks
{
    use AsAction;

    public function __construct(
        private CreateSignedDirectLink $createSignedDirectLink
    ) {}

    /**
     * Execute the action.
     */
    public function __invoke(
        array $episodes,
    ): Collection {
        return collect($episodes)
            ->map(fn (Episode $episode) => $this->createSignedDirectLink->run($episode));
    }
}