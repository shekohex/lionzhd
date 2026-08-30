<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Http\Integrations\LionzTv\Responses\VodInformation;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

final readonly class CreateSignedMediaLink
{
    public function __construct(
        private CreateXtreamcodesDownloadUrl $createXtreamcodesDownloadUrl,
    ) {}

    /**
     * @param  array<string, string>  $routeParameters
     */
    public function __invoke(
        VodInformation|Episode $data,
        string $routeName,
        string $cacheKeyPrefix,
        DateTimeInterface $expiresAt,
        array $routeParameters = [],
        string $purpose = 'media',
    ): string {
        $remoteUrl = ($this->createXtreamcodesDownloadUrl)($data);
        $token = (string) Str::ulid();

        Cache::put("{$cacheKeyPrefix}:{$token}", (string) $remoteUrl, $expiresAt);

        Log::info('Signed media link created', [
            'user_id' => auth()->id(),
            'purpose' => $purpose,
            'content_type' => $data instanceof VodInformation ? 'vod' : 'episode',
            'content_id' => $data instanceof VodInformation ? $data->vodId : $data->id,
            'token' => $token,
            'expires_at' => $expiresAt->format(DateTimeInterface::ATOM),
        ]);

        return URL::temporarySignedRoute($routeName, $expiresAt, [
            ...$routeParameters,
            'token' => $token,
        ]);
    }
}
