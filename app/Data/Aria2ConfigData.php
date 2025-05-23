<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class Aria2ConfigData extends Data
{
    public function __construct(
        public string $host,
        #[Between(1, 65535)]
        public int $port,
        public string $secret,
        public bool $use_ssl = false,
    ) {}
}
