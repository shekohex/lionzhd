<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\AsAction;
use App\Http\Integrations\LionzTv\Responses\Episode;
use App\Http\Integrations\LionzTv\Responses\VodInformation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * @method static string run(VodInformation|Episode $data)
 */
final readonly class CreateSignedDirectLink
{
    use AsAction;

    public function __construct(
        private CreateXtreamcodesDownloadUrl $createXtreamcodesDownloadUrl
    ) {}

    /**
     * Execute the action.
     */
    public function __invoke(
        VodInformation|Episode $data,
    ): string {
        $remoteUrl = $this->createXtreamcodesDownloadUrl->run($data);
        $token = Str::ulid();

        Cache::put("direct:link:{$token}", (string) $remoteUrl, now()->addHours(4));

        $userId = auth()->id();
        $contentType = $data instanceof VodInformation ? 'vod' : 'episode';
        $contentId = $data instanceof VodInformation ? $data->vodId : $data->id;

        Log::info('Direct download link created', [
            'user_id' => $userId,
            'content_type' => $contentType,
            'content_id' => $contentId,
            'token' => $token,
            'ttl' => '4 hours',
        ]);

        return URL::temporarySignedRoute('direct.resolve', now()->addHours(4), [
            'token' => $token,
        ]);
    }
}
