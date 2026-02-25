<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Integrations\LionzTv\Requests\GetSeriesCategoriesRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodCategoriesRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Jobs\SyncCategories;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class SyncCategoriesController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/synccategories');
    }

    public function update(Request $request, XtreamCodesConnector $connector): RedirectResponse
    {
        $forceEmptyVod = $request->boolean('forceEmptyVod');
        $forceEmptySeries = $request->boolean('forceEmptySeries');

        try {
            $preflight = $this->preflight($connector);
        } catch (Throwable $exception) {
            return back()->withErrors([
                'preflight' => sprintf('Unable to preflight categories: %s', $exception->getMessage()),
            ]);
        }

        $requiresVodConfirmation = $preflight['vod_count'] === 0 && ! $forceEmptyVod;
        $requiresSeriesConfirmation = $preflight['series_count'] === 0 && ! $forceEmptySeries;

        if ($requiresVodConfirmation || $requiresSeriesConfirmation) {
            $emptySources = array_values(array_filter([
                $requiresVodConfirmation ? 'VOD' : null,
                $requiresSeriesConfirmation ? 'Series' : null,
            ]));

            $warning = sprintf(
                '%s categories returned empty list. Confirm to queue sync with explicit force flags.',
                implode(' and ', $emptySources),
            );

            $errors = [
                'confirmation' => $warning,
            ];

            if ($requiresVodConfirmation) {
                $errors['forceEmptyVod'] = 'VOD categories returned empty list.';
            }

            if ($requiresSeriesConfirmation) {
                $errors['forceEmptySeries'] = 'Series categories returned empty list.';
            }

            return back()
                ->withInput()
                ->with('warning', $warning)
                ->withErrors($errors);
        }

        SyncCategories::dispatch(
            forceEmptyVod: $forceEmptyVod,
            forceEmptySeries: $forceEmptySeries,
            requestedByUserId: $request->user()?->id,
        );

        return back()->with('success', 'Category sync queued successfully.');
    }

    private function preflight(XtreamCodesConnector $connector): array
    {
        /** @var array<int, array<string, mixed>> $vodPayload */
        $vodPayload = $connector->send(new GetVodCategoriesRequest)->dtoOrFail();

        /** @var array<int, array<string, mixed>> $seriesPayload */
        $seriesPayload = $connector->send(new GetSeriesCategoriesRequest)->dtoOrFail();

        return [
            'vod_count' => $this->countValidCategoryRows($vodPayload),
            'series_count' => $this->countValidCategoryRows($seriesPayload),
        ];
    }

    private function countValidCategoryRows(array $payload): int
    {
        $count = 0;

        foreach ($payload as $row) {
            $categoryId = trim((string) ($row['category_id'] ?? ''));

            if ($categoryId !== '') {
                $count++;
            }
        }

        return $count;
    }
}
