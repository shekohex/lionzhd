<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Downloads\CancelDownload;
use App\Actions\Downloads\RetryDownload;
use App\Actions\GetDownloadStatus;
use App\Data\MediaDownloadStatusData;
use App\Enums\MediaDownloadAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\JsonRpcException;
use App\Http\Integrations\Aria2\Requests\PauseRequest;
use App\Http\Integrations\Aria2\Requests\RemoveDownloadResultRequest;
use App\Http\Integrations\Aria2\Requests\UnPauseRequest;
use App\Http\Integrations\Aria2\Responses\JsonRpcResponse;
use App\Http\Requests\Api\ListDownloadsRequest;
use App\Http\Requests\Api\UpdateDownloadRequest;
use App\Http\Resources\Api\MediaDownloadResource;
use App\Models\MediaDownloadRef;
use App\Models\User;
use App\Support\JsonApiErrorResponse;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class DownloadsController extends Controller
{
    public function index(ListDownloadsRequest $request, #[CurrentUser] User $user): AnonymousResourceCollection
    {
        $isAdmin = $user->role === UserRole::Admin;
        $ownerIds = $isAdmin ? $request->ownerIds() : [];

        $downloads = MediaDownloadRef::query()
            ->with('media')
            ->when($isAdmin, static fn ($query) => $query->with(['owner:id,name,email']))
            ->when($user->role === UserRole::Member, static fn ($query) => $query->where('user_id', $user->id))
            ->when($isAdmin && $ownerIds !== [], static fn ($query) => $query->whereIn('user_id', $ownerIds))
            ->orderByDesc('created_at')
            ->paginate(
                perPage: $request->pageSize(),
                pageName: 'page[number]',
                page: $request->pageNumber(),
            );

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

        $downloads->getCollection()->transform(function (MediaDownloadRef $download) use ($downloadStatus): MediaDownloadRef {
            $status = $downloadStatus->firstWhere('gid', $download->gid);

            if (is_array($status) && ! isset($status['error'])) {
                $download->setAttribute('api_download_status', MediaDownloadStatusData::from($status));
            }

            return $download;
        });

        /** @var AnonymousResourceCollection $collection */
        $collection = MediaDownloadResource::collection($downloads);

        return $collection->additional([
            'meta' => [
                'owner_options' => $isAdmin ? $this->ownerOptions() : [],
            ],
        ]);
    }

    public function update(UpdateDownloadRequest $request, JsonRpcConnector $connector, MediaDownloadRef $download): MediaDownloadResource
    {
        Gate::authorize('download-operations', $download);

        if ($download->canceled_at !== null) {
            throw new HttpResponseException(JsonApiErrorResponse::make(409, 'This download is already canceled and cannot be modified.'));
        }

        $action = $request->action();

        if ($action->isCancel()) {
            $this->cancel($download, $request->deletePartial());

            return new MediaDownloadResource($download->refresh()->load('media'));
        }

        if ($action->isRetry()) {
            if ($download->retry_next_at !== null && now()->lt($download->retry_next_at)) {
                throw new HttpResponseException(JsonApiErrorResponse::make(409, 'Retry is temporarily unavailable while this download is cooling down.'));
            }

            $error = RetryDownload::run($download, $request->restartFromZero(), true);

            if ($error !== null) {
                throw new HttpResponseException(JsonApiErrorResponse::make(422, $error));
            }

            return new MediaDownloadResource($download->refresh()->load('media'));
        }

        $removed = $this->applyAria2Action($connector, $download, $action);

        if ($removed) {
            return new MediaDownloadResource($download->load('media'));
        }

        return new MediaDownloadResource($download->refresh()->load('media'));
    }

    public function destroy(MediaDownloadRef $download): MediaDownloadResource
    {
        Gate::authorize('download-operations', $download);

        $this->cancel($download, false);

        return new MediaDownloadResource($download->refresh()->load('media'));
    }

    private function applyAria2Action(JsonRpcConnector $connector, MediaDownloadRef $download, MediaDownloadAction $action): bool
    {
        $result = GetDownloadStatus::run([$download->gid]);
        $errors = $result->filter(fn (mixed $response): bool => isset($response['error']))->map(fn (array $response): mixed => $response['error']);

        if ($errors->isNotEmpty()) {
            throw new HttpResponseException(JsonApiErrorResponse::make(422, (string) $errors->first()));
        }

        $data = MediaDownloadStatusData::from($result->first());

        if (! $data->status->canTakeAction($action)) {
            throw new HttpResponseException(JsonApiErrorResponse::make(409, "You cannot {$action->value} a download in {$data->status->value} status."));
        }

        $req = match ($action) {
            MediaDownloadAction::Pause => new PauseRequest($download->gid),
            MediaDownloadAction::Resume => new UnPauseRequest($download->gid),
            MediaDownloadAction::Remove => new RemoveDownloadResultRequest($download->gid),
            default => null,
        };

        if ($req === null) {
            throw new HttpResponseException(JsonApiErrorResponse::make(422, 'Unsupported download action.'));
        }

        /** @var JsonRpcResponse $response */
        $response = $connector->send($req)->dtoOrFail();

        if ($response->hasError()) {
            throw new HttpResponseException(JsonApiErrorResponse::make(422, $response->errorMessage()));
        }

        if ($action->isRemove()) {
            $download->delete();

            return true;
        }

        if ($action->isPause()) {
            $download->forceFill(['desired_paused' => true])->save();
        }

        if ($action->isResume()) {
            $download->forceFill(['desired_paused' => false])->save();
        }

        return false;
    }

    private function cancel(MediaDownloadRef $download, bool $deletePartial): void
    {
        $error = CancelDownload::run($download, $deletePartial);

        if ($error !== null) {
            throw new HttpResponseException(JsonApiErrorResponse::make(422, $error));
        }
    }

    /**
     * @return list<array{id: int, name: string, email: string}>
     */
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
