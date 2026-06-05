<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\AutoEpisodes\ManageSeriesMonitoring;
use App\Http\Controllers\Api\Concerns\ConvertsValidationExceptionToJsonApi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BackfillSeriesMonitoringRequest;
use App\Http\Resources\Api\SeriesMonitorResource;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SeriesMonitoringBackfillController extends Controller
{
    use ConvertsValidationExceptionToJsonApi;

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
}
