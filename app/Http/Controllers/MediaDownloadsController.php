<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\GetDownloadStatus;
use App\Data\EditMediaDownloadData;
use App\Data\MediaDownloadRefData;
use App\Data\MediaDownloadStatusData;
use App\Enums\MediaDownloadAction;
use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\Requests\PauseRequest;
use App\Http\Integrations\Aria2\Requests\RemoveDownloadResultRequest;
use App\Http\Integrations\Aria2\Requests\RemoveRequest;
use App\Http\Integrations\Aria2\Requests\UnPauseRequest;
use App\Http\Integrations\Aria2\Responses\JsonRpcResponse;
use App\Models\MediaDownloadRef;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class MediaDownloadsController extends Controller
{
    public function index(): Response
    {
        $downloads = MediaDownloadRef::query()
            ->with('media')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $downloadStatus = collect();
        if (! $downloads->isEmpty()) {
            $downloadStatus = GetDownloadStatus::run($downloads->pluck('gid')->toArray());
        }

        $data = MediaDownloadRefData::collect($downloads);

        $merged = $data->through(function (MediaDownloadRefData $item) use ($downloadStatus): MediaDownloadRefData {
            $status = $downloadStatus->firstWhere('gid', $item->gid);
            if ($status) {
                return $item->withDownloadStatus(MediaDownloadStatusData::from($status));
            }

            return $item;
        });

        return Inertia::render('downloads', [
            'downloads' => $merged,
        ]);
    }

    public function edit(JsonRpcConnector $connector, MediaDownloadRef $model, EditMediaDownloadData $payload): RedirectResponse
    {
        $result = GetDownloadStatus::run([$model->gid]);
        $errors = $result->filter(fn (mixed $response) => isset($response['error']))->map(fn (array $response) => $response['error']);

        if ($errors->isNotEmpty()) {
            return back()->withErrors($errors->toArray());
        }

        $data = MediaDownloadStatusData::from($result->first());
        $allowed = $data->status->canTakeAction($payload->action);

        if (! $allowed) {
            return back()->withErrors(['action' => "You cannot {$payload->action->value} a download in {$data->status->value} status."]);
        }

        $req = match ($payload->action) {
            MediaDownloadAction::Pause => new PauseRequest($model->gid),
            MediaDownloadAction::Resume => new UnPauseRequest($model->gid),
            MediaDownloadAction::Cancel => new RemoveRequest($model->gid),
            MediaDownloadAction::Remove => new RemoveDownloadResultRequest($model->gid),
            MediaDownloadAction::Retry => new RemoveDownloadResultRequest($model->gid),
        };

        /** @var JsonRpcResponse $response */
        $response = $connector->send($req)->dtoOrFail();
        if ($response->hasError()) {
            return back()->withErrors(['action' => $response->errorMessage()]);
        }

        // Other actions that we need to take.
        if ($payload->action->isRemove()) {
            $model->delete();
        }

        if ($payload->action->isRetry() && $model->isVodStream()) {
            $model->delete();

            return redirect()->route('movies.download', [
                'model' => $model->media_id,
            ]);
        }

        if ($payload->action->isRetry() && $model->isSeriesWithEpisode()) {
            $model->delete();

            return redirect()->route('series.download.single', [
                'model' => $model->media_id,
                'season' => $model->season,
                'episode' => $model->episode,
            ]);
        }

        return back()->with('success', 'Download status updated successfully.');
    }

    public function destroy(JsonRpcConnector $connector, MediaDownloadRef $model): RedirectResponse
    {
        $model->delete();
        $req = new RemoveDownloadResultRequest($model->gid);
        /** @var JsonRpcResponse $response */
        $response = $connector->send($req)->dtoOrFail();
        if ($response->hasError()) {
            return back()->withErrors(['action' => $response->errorMessage()]);
        }

        return back()->with('success', 'Download removed successfully.');
    }
}
