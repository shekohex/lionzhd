<?php

declare(strict_types=1);

namespace App\Http\Integrations\LionzTv\Responses;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class Movie
{
    public function __construct(
        public int $streamId,
        public string $name,
        public string $added,
        public string $categoryId,
        public string $containerExtension,
        public string $customSid,
        public string $directSource
    ) {}

    /**
     * @param  array<string,string|int>  $data
     */
    public static function fromJson(array $data): self
    {
        return new self(
            $data['stream_id'],
            $data['name'],
            $data['added'],
            $data['category_id'],
            $data['container_extension'],
            $data['custom_sid'],
            $data['direct_source']
        );
    }

    public static function fake(): self
    {
        return new self(
            fake()->randomNumber(),
            fake()->word(),
            fake()->dateTime()->format('Y-m-d H:i:s'),
            fake()->word(),
            fake()->word(),
            fake()->word(),
            fake()->word()
        );
    }
}
