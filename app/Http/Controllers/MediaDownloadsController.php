<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Downloads\ApplyDownloadAction;
use App\Actions\Downloads\CancelDownload;
use App\Actions\Downloads\RetryDownload as RetryDownloadAction;
use App\Actions\GetDownloadStatus;
use App\Data\EditMediaDownloadData;
use App\Data\MediaDownloadRefData;
use App\Data\MediaDownloadStatusData;
use App\Enums\UserRole;
use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\JsonRpcException;
use App\Models\MediaDownloadRef;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class MediaDownloadsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isAdmin = $user->role === UserRole::Admin;
        $ownerIds = $this->parseOwnerIds($isAdmin ? $request->query('owners') : null);

        $downloads = MediaDownloadRef::query()
            ->with('media')
            ->when($isAdmin, static fn ($query) => $query->with(['owner:id,name,email']))
            ->when($user->role === UserRole::Member, static fn ($query) => $query->where('user_id', $user->id))
            ->when($isAdmin && $ownerIds !== [], static fn ($query) => $query->whereIn('user_id', $ownerIds))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $downloadStatus = collect();
        if (! $downloads->isEmpty()) {
            try {
                $downloadStatus = GetDownloadStatus::run(
                    $downloads->pluck('gid')->toArray(),
                    ['gid', 'status', 'totalLength', 'completedLength', 'downloadSpeed', 'errorCode', 'errorMessage', 'dir', 'files'],
                );
            } catch (JsonRpcException) {
                $downloadStatus = collect();
            }
        }

        $data = MediaDownloadRefData::collect($downloads);

        $merged = $data->through(function (MediaDownloadRefData $item) use ($downloadStatus): MediaDownloadRefData {
            $status = $downloadStatus->firstWhere('gid', $item->gid);
            if ($status && ! isset($status['error'])) {
                return $item->withDownloadStatus(MediaDownloadStatusData::from($status));
            }

            return $item;
        });

        $props = [
            'downloads' => $merged,
        ];

        if ($isAdmin) {
            $props['ownerOptions'] = $this->ownerOptions();
        }

        return Inertia::render('downloads', $props);
    }

    public function edit(Request $request, JsonRpcConnector $connector, MediaDownloadRef $model, EditMediaDownloadData $payload): RedirectResponse
    {
        if ($model->canceled_at !== null) {
            return back()->withErrors(['action' => 'This download is already canceled and cannot be modified.']);
        }

        if ($payload->action->isCancel()) {
            $error = CancelDownload::run($model, $payload->delete_partial);

            if ($error !== null) {
                return back()->withErrors(['action' => $error]);
            }

            return back()->with('success', 'Download canceled successfully.');
        }

        if ($payload->action->isRetry()) {
            if ($model->retry_next_at !== null && now()->lt($model->retry_next_at)) {
                return back()->withErrors(['action' => 'Retry is temporarily unavailable while this download is cooling down.']);
            }

            $error = RetryDownloadAction::run($model, $payload->restart_from_zero, true);

            if ($error !== null) {
                return back()->withErrors(['action' => $error]);
            }

            return back()->with('success', 'Download retried successfully.');
        }

        $result = ApplyDownloadAction::run($connector, $model, $payload->action);

        if (! $result->ok) {
            return back()->withErrors(['action' => $result->error ?? 'Download status update failed.']);
        }

        return back()->with('success', 'Download status updated successfully.');
    }

    public function destroy(MediaDownloadRef $model): RedirectResponse
    {
        $error = CancelDownload::run($model, false);

        if ($error !== null) {
            return back()->withErrors(['action' => $error]);
        }

        return back()->with('success', 'Download canceled successfully.');
    }

    private function parseOwnerIds(mixed $owners): array
    {
        if (! is_string($owners) || $owners === '') {
            return [];
        }

        return collect(explode(',', $owners))
            ->map(static fn (string $id): string => mb_trim($id))
            ->filter(static fn (string $id): bool => $id !== '' && ctype_digit($id))
            ->map(static fn (string $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function ownerOptions(): array
    {
        return User::query()
            ->select(['id', 'name', 'email'])
            ->whereIn('id', MediaDownloadRef::query()->select('user_id')->whereNotNull('user_id')->distinct())
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(static fn (User $owner): array => [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
            ])
            ->values()
            ->all();
    }
}
