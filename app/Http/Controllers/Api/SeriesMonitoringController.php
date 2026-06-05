<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\AutoEpisodes\ManageSeriesMonitoring;
use App\Http\Controllers\Api\Concerns\ConvertsValidationExceptionToJsonApi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteSeriesMonitoringRequest;
use App\Http\Requests\Api\StoreSeriesMonitoringRequest;
use App\Http\Requests\Api\UpdateSeriesMonitoringRequest;
use App\Http\Resources\Api\SeriesMonitorResource;
use App\Models\AutoEpisodes\SeriesMonitor;
use App\Models\Series;
use App\Models\User;
use App\Support\JsonApiErrorResponse;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SeriesMonitoringController extends Controller
{
    use ConvertsValidationExceptionToJsonApi;

    public function show(#[CurrentUser] User $user, Series $series): SeriesMonitorResource
    {
        $monitor = ManageSeriesMonitoring::make()->monitorForSeries($user, $series);

        if (! $monitor instanceof SeriesMonitor) {
            throw new HttpResponseException(JsonApiErrorResponse::make(404, 'Monitoring has not been enabled for this series.'));
        }

        return new SeriesMonitorResource($monitor->load('series'));
    }

    public function store(StoreSeriesMonitoringRequest $request, #[CurrentUser] User $user, Series $series): SeriesMonitorResource
    {
        Gate::authorize('auto-download-schedules');

        try {
            $monitor = ManageSeriesMonitoring::make()->store($user, $series, $request->validated());
        } catch (ValidationException $exception) {
            throw new HttpResponseException($this->validationError($exception));
        }

        return new SeriesMonitorResource($monitor->refresh()->load('series'));
    }

    public function update(UpdateSeriesMonitoringRequest $request, #[CurrentUser] User $user, Series $series): SeriesMonitorResource
    {
        Gate::authorize('auto-download-schedules');

        try {
            $monitor = ManageSeriesMonitoring::make()->update($user, $series, $request->validated());
        } catch (ValidationException $exception) {
            throw new HttpResponseException($this->validationError($exception));
        }

        return new SeriesMonitorResource($monitor->refresh()->load('series'));
    }

    public function destroy(DeleteSeriesMonitoringRequest $request, #[CurrentUser] User $user, Series $series): SeriesMonitorResource
    {
        Gate::authorize('auto-download-schedules');

        try {
            $monitor = ManageSeriesMonitoring::make()->disable($user, $series, $request->removeFromWatchlist());
        } catch (ValidationException $exception) {
            throw new HttpResponseException($this->validationError($exception, 404));
        }

        return new SeriesMonitorResource($monitor);
    }
}
