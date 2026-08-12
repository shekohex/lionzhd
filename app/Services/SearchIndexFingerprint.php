<?php

declare(strict_types=1);

namespace App\Services;

final class SearchIndexFingerprint
{
    /**
     * @param  iterable<int, array<string, mixed>>  $documents
     */
    public function forDocuments(iterable $documents): string
    {
        $documentHashes = [];

        foreach ($documents as $document) {
            $documentHashes[] = hash('sha256', json_encode(
                $this->normalize($document),
                JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION,
            ));
        }

        sort($documentHashes, SORT_STRING);

        return hash('sha256', implode('', $documentHashes));
    }

    private function normalize(mixed $value): mixed
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
    }
}
