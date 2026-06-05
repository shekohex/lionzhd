<?php

declare(strict_types=1);

namespace App\Actions\Downloads;

use App\Actions\GetDownloadStatus;
use App\Concerns\AsAction;
use App\Data\MediaDownloadStatusData;
use App\Enums\MediaDownloadAction;
use App\Http\Integrations\Aria2\JsonRpcConnector;
use App\Http\Integrations\Aria2\JsonRpcException;
use App\Http\Integrations\Aria2\Requests\PauseRequest;
use App\Http\Integrations\Aria2\Requests\RemoveDownloadResultRequest;
use App\Http\Integrations\Aria2\Requests\UnPauseRequest;
use App\Http\Integrations\Aria2\Responses\JsonRpcResponse;
use App\Models\MediaDownloadRef;

final class ApplyDownloadAction
{
    use AsAction;

    public function __invoke(JsonRpcConnector $connector, MediaDownloadRef $download, MediaDownloadAction $action): DownloadActionResult
    {
        try {
            $result = GetDownloadStatus::run([$download->gid]);
        } catch (JsonRpcException $exception) {
            return DownloadActionResult::unavailable($exception);
        }

        $errors = $result->filter(fn (mixed $response): bool => isset($response['error']))->map(fn (array $response): mixed => $response['error']);

        if ($errors->isNotEmpty()) {
            return DownloadActionResult::failed((string) $errors->first());
        }

        $data = MediaDownloadStatusData::from($result->first());

        if (! $data->status->canTakeAction($action)) {
            return DownloadActionResult::conflict("You cannot {$action->value} a download in {$data->status->value} status.");
        }

        $req = match ($action) {
            MediaDownloadAction::Pause => new PauseRequest($download->gid),
            MediaDownloadAction::Resume => new UnPauseRequest($download->gid),
            MediaDownloadAction::Remove => new RemoveDownloadResultRequest($download->gid),
            default => null,
        };

        if ($req === null) {
            return DownloadActionResult::failed('Unsupported download action.');
        }

        try {
            /** @var JsonRpcResponse $response */
            $response = $connector->send($req)->dtoOrFail();
        } catch (JsonRpcException $exception) {
            return DownloadActionResult::unavailable($exception);
        }

        if ($response->hasError()) {
            return DownloadActionResult::failed($response->errorMessage());
        }

        if ($action->isRemove()) {
            $download->delete();

            return DownloadActionResult::removed();
        }

        if ($action->isPause()) {
            $download->forceFill(['desired_paused' => true])->save();
        }

        if ($action->isResume()) {
            $download->forceFill(['desired_paused' => false])->save();
        }

        return DownloadActionResult::succeeded();
    }
}
