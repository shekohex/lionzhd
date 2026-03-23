import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import type { SeriesInformationPageProps } from '@/types/series';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { useCallback, useMemo, useState } from 'react';
import { ErrorBoundary, FallbackProps } from 'react-error-boundary';
import { toast } from 'sonner';

// Custom components
import MonitoringCard from '@/components/auto-episodes/monitoring-card';
import ScheduleEditorDialog, {
    type ScheduleEditorSubmitPayload,
} from '@/components/auto-episodes/schedule-editor-dialog';
import CastList from '@/components/cast-list';
import EpisodeList from '@/components/episode-list';
import MediaHeroSection from '@/components/media-hero-section';
import VideoTrailerPreview from '@/components/video-trailer-preview';

// Error fallback component
function ErrorFallback({ error, resetErrorBoundary }: FallbackProps) {
    return (
        <div className="flex flex-col items-center justify-center rounded-lg border border-red-300 bg-red-50 p-4 text-center text-red-800">
            <p className="text-lg font-medium">Something went wrong:</p>
            <pre className="mt-2 overflow-auto text-sm">{error.message}</pre>
            <button
                onClick={resetErrorBoundary}
                className="mt-4 rounded bg-red-600 px-4 py-2 text-white transition-colors hover:bg-red-700"
            >
                Try again
            </button>
        </div>
    );
}

export default function SeriesInformation() {
    const { props } = usePage<SeriesInformationPageProps>();
    const { info, in_watchlist, auth, monitor, category_context, preset_times, backfill_preset_counts, run_now_cooldown_seconds } = props;
    const isAdmin = auth.user.role === 'admin';
    const isInternalMember = auth.user.role === 'member' && auth.user.subtype === 'internal';
    const isExternalMember = auth.user.role === 'member' && auth.user.subtype === 'external';
    const canManageMonitoring = isAdmin || isInternalMember;
    const monitoringLockedReason = isExternalMember
        ? 'External members can view monitoring status only. Contact your super-admin to enable schedule controls.'
        : 'Monitoring controls are unavailable for your account.';
    const serverDownloadVisibility = isAdmin || isInternalMember ? 'enabled' : isExternalMember ? 'disabled' : 'hidden';

    const { post: addToWatchlistCall, delete: removeFromWatchlistCall } = useForm();
    const { delete: forgetCache } = useForm();
    const [scheduleEditorOpen, setScheduleEditorOpen] = useState(false);
    const [scheduleEditorMode, setScheduleEditorMode] = useState<'enable' | 'edit'>('enable');
    const [monitoringProcessing, setMonitoringProcessing] = useState(false);

    // State for trailer modal
    const [isTrailerOpen, setIsTrailerOpen] = useState(false);

    const availableSeasons = useMemo(
        () =>
            Object.keys(info.seasonsWithEpisodes)
                .map((season) => Number.parseInt(season, 10))
                .filter((season) => Number.isInteger(season) && season > 0)
                .sort((a, b) => a - b),
        [info.seasonsWithEpisodes],
    );

    const runNowCooldownLabel = useMemo(() => {
        if (run_now_cooldown_seconds >= 3600) {
            const hours = Math.floor(run_now_cooldown_seconds / 3600);
            const minutes = Math.ceil((run_now_cooldown_seconds % 3600) / 60);
            return minutes > 0 ? `${hours}h ${minutes}m` : `${hours}h`;
        }

        if (run_now_cooldown_seconds >= 60) {
            return `${Math.ceil(run_now_cooldown_seconds / 60)}m`;
        }

        return `${Math.max(1, run_now_cooldown_seconds)}s`;
    }, [run_now_cooldown_seconds]);

    // Get release year from full date
    const releaseYear = info.releaseDate ? new Date(info.releaseDate).getFullYear() : null;

    // Handle play functionality
    const handlePlay = useCallback(() => {
        // Get first episode of the first season
        const seasonNumbers = Object.keys(info.seasonsWithEpisodes)
            .map(Number)
            .sort((a, b) => a - b);

        if (seasonNumbers.length > 0) {
            const firstSeason = seasonNumbers[0];
            const episodes = info.seasonsWithEpisodes[firstSeason];

            if (episodes && episodes.length > 0) {
                // Here you would typically trigger playback of the first episode
                console.log('Playing first episode:', episodes[0]);

                // For demonstration, let's open the trailer instead if there's no actual playback
                if (info.youtubeTrailer) {
                    setIsTrailerOpen(true);
                }
            }
        }
    }, [info]);

    // Handle downloading a specific episode
    const handleDownloadEpisode = useCallback(
        (episodeIndex: number, episode: App.Http.Integrations.LionzTv.Responses.Episode) => {
            router.visit(
                route('series.download.single', {
                    model: info.seriesId,
                    season: episode.season,
                    episode: episodeIndex,
                }),
                {
                    preserveScroll: true,
                    preserveState: false,
                },
            );
        },
        [info.seriesId],
    );

    // Handle direct downloading a specific episode (open in new tab)
    const handleDirectDownloadEpisode = useCallback(
        (episodeIndex: number, episode: App.Http.Integrations.LionzTv.Responses.Episode) => {
            const url = route('series.direct.single', {
                model: info.seriesId,
                season: episode.season,
                episode: episodeIndex,
            });
            window.open(url, '_blank', 'noopener');
        },
        [info.seriesId],
    );

    const handleDownloadSelectedEpisodes = useCallback(
        (selectedEpisodes: App.Data.SelectedEpisodeData[]) => {
            router.post(
                route('series.download.batch', { model: info.seriesId }),
                {
                    selectedEpisodes,
                },
                {
                    preserveScroll: true,
                    preserveState: false,
                },
            );
        },
        [info.seriesId],
    );

    const handleDirectDownloadSelectedEpisodes = useCallback(
        (selectedEpisodes: App.Data.SelectedEpisodeData[]) => {
            router.post(
                route('series.direct.batch', { model: info.seriesId }),
                {
                    selectedEpisodes,
                },
                {
                    preserveScroll: true,
                    preserveState: false,
                },
            );
        },
        [info.seriesId],
    );

    const handleBlockedServerDownload = useCallback(() => {
        toast.info('Server download unavailable', {
            description: 'Use Direct Download and contact your super-admin to request server-download access.',
        });
    }, []);

    // Handle trailer button click
    const handleTrailerClick = useCallback(() => {
        setIsTrailerOpen(true);
    }, []);

    const addToWatchlist = useCallback(() => {
        addToWatchlistCall(route('series.watchlist', { model: info.seriesId }), {
            preserveScroll: true,
            preserveState: true,
        });
    }, [addToWatchlistCall, info.seriesId]);

    const removeFromWatchlist = useCallback(() => {
        removeFromWatchlistCall(route('series.watchlist.destroy', { model: info.seriesId }), {
            preserveScroll: true,
            preserveState: true,
        });
    }, [removeFromWatchlistCall, info.seriesId]);

    const handleForgetCache = useCallback(() => {
        forgetCache(route('series.cache', { model: info.seriesId }), { preserveScroll: true, preserveState: false });
    }, [forgetCache, info.seriesId]);

    const handleBlockedMonitoringAction = useCallback(() => {
        toast.info('Monitoring controls are locked', {
            description: monitoringLockedReason,
        });
    }, [monitoringLockedReason]);

    const openScheduleEditor = useCallback(
        (mode: 'enable' | 'edit') => {
            if (!canManageMonitoring || monitoringProcessing) {
                handleBlockedMonitoringAction();
                return;
            }

            if (mode === 'enable' && !in_watchlist) {
                toast.info('Add to watchlist first', {
                    description: 'Monitoring can only be enabled for watchlisted series.',
                });
                return;
            }

            setScheduleEditorMode(mode);
            setScheduleEditorOpen(true);
        },
        [canManageMonitoring, handleBlockedMonitoringAction, in_watchlist, monitoringProcessing],
    );

    const handleMonitoringSubmit = useCallback(
        (payload: ScheduleEditorSubmitPayload) => {
            if (!canManageMonitoring || monitoringProcessing) {
                handleBlockedMonitoringAction();
                return;
            }

            setMonitoringProcessing(true);

            if (scheduleEditorMode === 'enable') {
                let backfillTriggered = false;

                router.post(route('series.monitoring.store', { model: info.seriesId }), payload.monitor, {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        setScheduleEditorOpen(false);

                        if (typeof payload.backfill_count === 'number' && payload.backfill_count > 0) {
                            backfillTriggered = true;

                            router.post(
                                route('series.monitoring.backfill', { model: info.seriesId }),
                                {
                                    backfill_count: payload.backfill_count,
                                },
                                {
                                    preserveScroll: true,
                                    preserveState: true,
                                    onFinish: () => {
                                        setMonitoringProcessing(false);
                                    },
                                },
                            );
                        }
                    },
                    onFinish: () => {
                        if (!backfillTriggered) {
                            setMonitoringProcessing(false);
                        }
                    },
                });

                return;
            }

            router.patch(route('series.monitoring.update', { model: info.seriesId }), payload.monitor, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setScheduleEditorOpen(false);
                },
                onFinish: () => {
                    setMonitoringProcessing(false);
                },
            });
        },
        [
            canManageMonitoring,
            handleBlockedMonitoringAction,
            info.seriesId,
            monitoringProcessing,
            scheduleEditorMode,
        ],
    );

    const handleDisableMonitoring = useCallback(
        (removeFromWatchlist: boolean) => {
            if (!canManageMonitoring || monitoringProcessing) {
                handleBlockedMonitoringAction();
                return;
            }

            setMonitoringProcessing(true);

            router.delete(route('series.monitoring.destroy', { model: info.seriesId }), {
                data: {
                    remove_from_watchlist: removeFromWatchlist,
                },
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    setMonitoringProcessing(false);
                },
            });
        },
        [canManageMonitoring, handleBlockedMonitoringAction, info.seriesId, monitoringProcessing],
    );

    const handleRunNow = useCallback(() => {
        if (!canManageMonitoring || monitoringProcessing) {
            handleBlockedMonitoringAction();
            return;
        }

        setMonitoringProcessing(true);

        router.post(
            route('series.monitoring.run-now', { model: info.seriesId }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    setMonitoringProcessing(false);
                },
            },
        );
    }, [canManageMonitoring, handleBlockedMonitoringAction, info.seriesId, monitoringProcessing]);

    // Define breadcrumbs for navigation
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Series',
            href: '/series',
        },
        {
            title: info.name,
            href: `/series/${info.seriesId}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${info.name} | Series Information`} />

            <ErrorBoundary FallbackComponent={ErrorFallback}>
                <div className="relative w-full">
                    {/* Hero Section with fallback image handling */}
                    <MediaHeroSection
                        title={info.name}
                        description={info.plot}
                        releaseYear={releaseYear ?? ''}
                        rating={info.rating_5based}
                        duration={info.episodeRunTime}
                        genres={info.genre}
                        categoryContext={category_context}
                        backdropUrl={info.backdropPath?.length > 0 ? info.backdropPath[0] : null}
                        posterUrl={info.cover}
                        additionalBackdrops={info.backdropPath?.slice(1) || []}
                        trailerUrl={info.youtubeTrailer}
                        onPlay={handlePlay}
                        onTrailerPlay={handleTrailerClick}
                        onForgetCache={handleForgetCache}
                        onAddToWatchlist={addToWatchlist}
                        onRemoveFromWatchlist={removeFromWatchlist}
                        inMyWatchlist={in_watchlist}
                    />

                    {/* Main Content Section */}
                    <div className="mx-auto max-w-7xl px-3 py-6 md:px-4 md:py-12">
                        <div className="space-y-6 md:space-y-16">
                            <AnimatePresence>
                                <motion.div
                                    initial={{ opacity: 0, y: 20 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.6 }}
                                >
                                    <EpisodeList
                                        seasonsWithEpisodes={info.seasonsWithEpisodes}
                                        onDownloadEpisode={handleDownloadEpisode}
                                        onDirectDownloadEpisode={handleDirectDownloadEpisode}
                                        onDownloadSelected={handleDownloadSelectedEpisodes}
                                        onDirectDownloadSelected={handleDirectDownloadSelectedEpisodes}
                                        serverDownloadVisibility={serverDownloadVisibility}
                                        serverDownloadDisabledReason={
                                            isExternalMember
                                                ? 'External members can use Direct Download only. Contact your super-admin for access.'
                                                : undefined
                                        }
                                        onServerDownloadBlocked={handleBlockedServerDownload}
                                    />
                                </motion.div>
                            </AnimatePresence>

                            <AnimatePresence>
                                <motion.div
                                    initial={{ opacity: 0, y: 20 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.6, delay: 0.1 }}
                                    className="space-y-3"
                                >
                                    <MonitoringCard
                                        monitor={monitor}
                                        inWatchlist={in_watchlist}
                                        canManage={canManageMonitoring}
                                        disabledReason={monitoringLockedReason}
                                        processing={monitoringProcessing}
                                        onEnable={() => openScheduleEditor('enable')}
                                        onEdit={() => openScheduleEditor('edit')}
                                        onRunNow={handleRunNow}
                                        onDisable={handleDisableMonitoring}
                                    />
                                    <p className="text-xs text-muted-foreground">Run now cooldown: {runNowCooldownLabel}</p>
                                </motion.div>
                            </AnimatePresence>

                            <AnimatePresence>
                                <motion.div
                                    initial={{ opacity: 0, y: 20 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.6, delay: 0.2 }}
                                >
                                    <CastList cast={info.cast} director={info.director} />
                                </motion.div>
                            </AnimatePresence>
                        </div>
                    </div>

                    <ScheduleEditorDialog
                        open={scheduleEditorOpen}
                        onOpenChange={setScheduleEditorOpen}
                        mode={scheduleEditorMode}
                        monitor={monitor}
                        availableSeasons={availableSeasons}
                        presetTimes={preset_times}
                        backfillPresetCounts={backfill_preset_counts}
                        disabled={!canManageMonitoring}
                        disabledReason={!canManageMonitoring ? monitoringLockedReason : undefined}
                        submitting={monitoringProcessing}
                        onSubmit={handleMonitoringSubmit}
                    />

                    <VideoTrailerPreview
                        trailerUrl={info.youtubeTrailer}
                        isOpen={isTrailerOpen}
                        onClose={() => setIsTrailerOpen(false)}
                    />
                </div>
            </ErrorBoundary>
        </AppLayout>
    );
}
