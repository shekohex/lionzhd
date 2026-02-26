<?php

declare(strict_types=1);

namespace App\Actions\Downloads;

use App\Concerns\AsAction;
use Illuminate\Support\Facades\File;

/**
 * @method static null|string run(array $paths, ?string $downloadDir = null)
 */
final readonly class DeleteDownloadFiles
{
    use AsAction;

    /**
     * @param  list<string>  $paths
     */
    public function __invoke(array $paths, ?string $downloadDir = null): ?string
    {
        $allowlistedRoot = config('services.aria2.download_root');
        $allowlistedRootRealPath = is_string($allowlistedRoot) ? realpath($allowlistedRoot) : false;

        if (! is_string($allowlistedRootRealPath)) {
            return 'Delete partial data is unavailable because the server download root is not configured correctly.';
        }

        if ($downloadDir !== null && ! $this->isPathWithinRoot($downloadDir, $allowlistedRootRealPath)) {
            return 'Delete partial data was blocked because the download path is outside the allowed download root.';
        }

        foreach ($paths as $path) {
            $this->deleteFileIfWithinRoot($path, $allowlistedRootRealPath);
            $this->deleteFileIfWithinRoot("{$path}.aria2", $allowlistedRootRealPath);
        }

        return null;
    }

    private function deleteFileIfWithinRoot(string $path, string $allowlistedRootRealPath): void
    {
        $resolvedPath = realpath($path);

        if (! is_string($resolvedPath) || ! is_file($resolvedPath)) {
            return;
        }

        if (! $this->isPathWithinRoot($resolvedPath, $allowlistedRootRealPath)) {
            return;
        }

        File::delete($resolvedPath);
    }

    private function isPathWithinRoot(string $path, string $allowlistedRootRealPath): bool
    {
        $resolvedPath = realpath($path);

        if (! is_string($resolvedPath)) {
            return false;
        }

        $normalizedRoot = rtrim($allowlistedRootRealPath, DIRECTORY_SEPARATOR);

        return $resolvedPath === $normalizedRoot || str_starts_with($resolvedPath, $normalizedRoot.DIRECTORY_SEPARATOR);
    }
}
