<?php

declare(strict_types=1);

use App\Jobs\AutoEpisodes\DispatchDueMonitors;
use App\Jobs\RefreshMediaContents;
use App\Jobs\MonitorDownloads;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

// Schedule the RefreshMediaContents job to run daily at midnight UTC
Schedule::job(RefreshMediaContents::class)
    ->name('refresh-media-contents')
    ->description('Refresh media contents')
    ->dailyAt('00:00')
    ->timezone(new DateTimeZone('UTC'))
    ->withoutOverlapping()
    ->onFailure(fn () => Log::error('Failed to refresh media contents'))
    ->onSuccess(fn () => Log::info('Successfully refreshed media contents'))
    ->sentryMonitor();

Schedule::command('telescope:prune --hours=720')->dailyAt('00:00')
    ->timezone(new DateTimeZone('UTC'))
    ->withoutOverlapping()
    ->onFailure(fn () => Log::error('Failed to prune Telescope entries'))
    ->onSuccess(fn () => Log::info('Successfully pruned Telescope entries'));

Schedule::job(MonitorDownloads::class)
    ->name('monitor-downloads')
    ->description('Monitor aria2 downloads and schedule retries')
    ->everyMinute()
    ->withoutOverlapping()
    ->sentryMonitor();

Schedule::job(DispatchDueMonitors::class)
    ->name('auto-episodes-dispatch')
    ->description('Dispatch due series monitoring scans')
    ->everyMinute()
    ->withoutOverlapping()
    ->sentryMonitor();
