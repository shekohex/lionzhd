<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\AutoEpisodes\ManageSeriesMonitoring;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BackfillSeriesMonitoringRequest;
use App\Http\Resources\Api\SeriesMonitorResource;
use App\Models\Series;
use App\Models\User;
use App\Support\JsonApiErrorResponse;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SeriesMonitoringBackfillController extends Controller
{
    public function __invoke(BackfillSeriesMonitoringRequest $request, #[CurrentUser] User $user, Series $series): SeriesMonitorResource
    {
        Gate::authorize('auto-download-schedules');

        try {
            $monitor = ManageSeriesMonitoring::make()->backfill($user, $series, $request->integer('backfill_count'));
        } catch (ValidationException $exception) {
            throw new HttpResponseException($this->validationError($exception));
        }

        return new SeriesMonitorResource($monitor->refresh()->load('series'));
    }

    private function validationError(ValidationException $exception): JsonResponse
    {
        $errors = $exception->errors();
        $parameter = array_key_first($errors) ?? 'series';
        $detail = (string) ($errors[$parameter][0] ?? $exception->getMessage());

        return JsonApiErrorResponse::make(422, $detail, sourceParameter: (string) $parameter);
    }
}
