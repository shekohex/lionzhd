<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class DownloadedFileData extends Data
{
    public function __construct(
        /**
         * The index of the file
         */
        public int $index,

        /**
         * The path of the file
         */
        public string $path,

        /**
         * The length of the file (in bytes)
         */
        public int $length,

        /**
         * The completed length of the file (in bytes)
         */
        public int $completedLength,

        /**
         * The selected status of the file
         */
        public bool $selected = false,

        /**
         * The URIs of the file
         *
         * @var list<mixed>
         */
        #[LiteralTypeScriptType('{status: string, uri: string}[]')]
        public array $uris = []
    ) {}
}
