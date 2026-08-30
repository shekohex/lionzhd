<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Http\Integrations\LionzTv\Responses\VodInformation;

/**
 * @method static string run(VodInformation|Episode $data)
 */
final readonly class CreateSignedPlaybackLink
{
    use AsAction;

    public function __construct(
        private CreateSignedMediaLink $createSignedMediaLink,
    ) {}

    public function __invoke(VodInformation|Episode $data): string
    {
        $mediaType = $data instanceof VodInformation ? 'movie' : 'episode';
        $expiresAt = now()->addMinutes(max(1, (int) config('features.playback_link_ttl_minutes', 30)));

        return ($this->createSignedMediaLink)(
            data: $data,
            routeName: 'playback.resolve',
            cacheKeyPrefix: "playback:link:{$mediaType}",
            expiresAt: $expiresAt,
            routeParameters: ['mediaType' => $mediaType],
            purpose: 'playback',
        );
    }
}
