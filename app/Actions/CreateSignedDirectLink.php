<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Http\Integrations\LionzTv\Responses\VodInformation;

/**
 * @method static string run(VodInformation|Episode $data)
 */
final readonly class CreateSignedDirectLink
{
    use AsAction;

    public function __construct(
        private CreateSignedMediaLink $createSignedMediaLink,
    ) {}

    /**
     * Execute the action.
     */
    public function __invoke(
        VodInformation|Episode $data,
    ): string {
        return ($this->createSignedMediaLink)(
            data: $data,
            routeName: 'direct.resolve',
            cacheKeyPrefix: 'direct:link',
            expiresAt: now()->addHours(4),
            purpose: 'direct-download',
        );
    }
}
