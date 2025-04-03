<?php

declare(strict_types=1);

namespace App\Http\Controllers\VodStream;

use App\Actions\CreateDownloadDir;
use App\Actions\CreateXtreamcodesDownloadUrl;
use App\Actions\DownloadMedia;
use App\Actions\GetActiveDownloads;
use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\MediaDownloadRef;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class VodStreamDownloadController extends Controller
{
    /**
     * Trigger a download of the Video on demand stream.
     */
    public function create(#[CurrentUser] User $user, XtreamCodesConnector $client, VodStream $model): RedirectResponse
    {

        $vod = $client->send(new GetVodInfoRequest($model->stream_id));
        $dto = $vod->dtoOrFail();
        // Check if the user has already downloaded this stream and the download is still active
        $firstActive = GetActiveDownloads::run($model);
        if ($firstActive) {
            return redirect()->route('downloads', [
                'downloadable_id' => $model->stream_id,
                'gid' => $firstActive->gid,
            ]);
        }

        $url = CreateXtreamcodesDownloadUrl::run($dto);
        $gid = DownloadMedia::run($url, ['dir' => CreateDownloadDir::run($dto)]);

        MediaDownloadRef::fromVodStream($gid, $model)->saveOrFail();

        return redirect()->route('downloads', [
            'downloadable_id' => $model->stream_id,
            'gid' => $gid,
        ]);
    }
}
