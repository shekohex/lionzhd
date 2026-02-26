<?php

declare(strict_types=1);

namespace App\Actions\Downloads;

use App\Concerns\AsAction;

/**
 * @method static array{isTransient: bool, errorCode: int, errorMessage: ?string} run(int $errorCode, ?string $errorMessage = null)
 */
final readonly class ClassifyDownloadFailure
{
    use AsAction;

    /**
     * @var list<int>
     */
    private const array TRANSIENT_ERROR_CODES = [2, 6, 19, 29];

    /**
     * @return array{isTransient: bool, errorCode: int, errorMessage: ?string}
     */
    public function __invoke(int $errorCode, ?string $errorMessage = null): array
    {
        $isTransient = in_array($errorCode, self::TRANSIENT_ERROR_CODES, true)
            || $this->containsHttp5xx($errorMessage);

        return [
            'isTransient' => $isTransient,
            'errorCode' => $errorCode,
            'errorMessage' => $errorMessage,
        ];
    }

    private function containsHttp5xx(?string $errorMessage): bool
    {
        if (! is_string($errorMessage) || $errorMessage === '') {
            return false;
        }

        return preg_match('/\b(?:HTTP\s*)?5\d{2}\b/i', $errorMessage) === 1;
    }
}
