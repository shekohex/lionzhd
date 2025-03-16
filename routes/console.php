<?php

declare(strict_types=1);

use App\Jobs\RefreshMediaContents;
use Illuminate\Support\Facades\Schedule;

// Schedule the RefreshMediaContents job to run daily at midnight UTC
Schedule::job(app(RefreshMediaContents::class))
    ->dailyAt('00:00')
    ->timezone(new DateTimeZone('UTC'))
    ->withoutOverlapping()
    ->onFailure(fn () => Log::error('Failed to refresh media contents'))
    ->onSuccess(fn () => Log::info('Successfully refreshed media contents'));

Schedule::command('telescope:prune --hours=720')->dailyAt('00:00')
    ->timezone(new DateTimeZone('UTC'))
    ->withoutOverlapping()
    ->onFailure(fn () => Log::error('Failed to prune Telescope entries'))
    ->onSuccess(fn () => Log::info('Successfully pruned Telescope entries'));
