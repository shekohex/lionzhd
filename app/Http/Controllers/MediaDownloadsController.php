<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\GetDownloadStatus;
use App\Data\MediaDownloadRefData;
use App\Data\MediaDownloadStatusData;
use App\Models\MediaDownloadRef;
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

        $merged = $data->through(function (MediaDownloadRefData $item) use ($downloadStatus) {
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
}
