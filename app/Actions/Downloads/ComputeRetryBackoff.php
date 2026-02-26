<?php

declare(strict_types=1);

namespace App\Actions\Downloads;

use App\Concerns\AsAction;

/**
 * @method static int run(int $attempt)
 */
final readonly class ComputeRetryBackoff
{
    use AsAction;

    private const int BASE_SECONDS = 5;

    private const int MAX_SECONDS = 300;

    public function __invoke(int $attempt): int
    {
        $normalizedAttempt = max($attempt, 1);
        $backoff = self::BASE_SECONDS * (2 ** ($normalizedAttempt - 1));

        return min($backoff, self::MAX_SECONDS);
    }
}
