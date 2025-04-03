<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\GetDownloadStatus;
use App\Data\MediaDownloadRefData;
use App\Data\MediaDownloadStatusData;
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

    public function destroy(MediaDownloadRef $model): RedirectResponse
    {
        $model->delete();

        return back();
    }
}
