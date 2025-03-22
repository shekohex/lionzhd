import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import type { SeriesInformationPageProps } from '@/types/series';
import { Head, useForm, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { useCallback, useState } from 'react';
import { ErrorBoundary, FallbackProps } from 'react-error-boundary';

// Custom components
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
    const { info, in_watchlist } = props;

    const { post: addToWatchlistCall, delete: removeFromWatchlistCall } = useForm();
    const { delete: forgetCache } = useForm();

    // State for trailer modal
    const [isTrailerOpen, setIsTrailerOpen] = useState(false);

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

    // Handle playing a specific episode
    const handlePlayEpisode = useCallback((episode: App.Http.Integrations.LionzTv.Responses.Episode) => {
        console.log('Playing episode:', episode);
        // Here you would implement actual episode playback
    }, []);

    // Handle trailer button click
    const handleTrailerClick = useCallback(() => {
        setIsTrailerOpen(true);
    }, []);

    const addToWatchlist = useCallback(() => {
        addToWatchlistCall(route('series.watchlist', { model: info.seriesId }), {
            preserveScroll: true,
            preserveState: false,
        });
    }, [addToWatchlistCall, info.seriesId]);

    const removeFromWatchlist = useCallback(() => {
        removeFromWatchlistCall(route('series.watchlist', { model: info.seriesId }), {
            preserveScroll: true,
            preserveState: false,
        });
    }, [removeFromWatchlistCall, info.seriesId]);

    const handleForgetCache = useCallback(() => {
        forgetCache(route('series.cache', { model: info.seriesId }), { preserveScroll: true, preserveState: false });
    }, [forgetCache, info.seriesId]);
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
                    <div className="mx-auto max-w-7xl px-4 py-12">
                        <div className="space-y-16">
                            {/* Episodes Section */}
                            <AnimatePresence>
                                <motion.div
                                    initial={{ opacity: 0, y: 20 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.6 }}
                                >
                                    <EpisodeList
                                        seasonsWithEpisodes={info.seasonsWithEpisodes}
                                        onPlayEpisode={handlePlayEpisode}
                                    />
                                </motion.div>
                            </AnimatePresence>

                            {/* Cast & Crew Section */}
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

                    {/* Trailer Video Modal */}
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
