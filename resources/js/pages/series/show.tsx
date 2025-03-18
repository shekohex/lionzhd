import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import type { SeasonWithEpisodes, SeriesInformationPageProps } from '@/types/series';
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
    const { series, in_watchlist, num } = props;

    const { post: addToWatchlistCall, delete: removeFromWatchlistCall } = useForm();

    // State for trailer modal
    const [isTrailerOpen, setIsTrailerOpen] = useState(false);

    // Get release year from full date
    const releaseYear = series.releaseDate ? new Date(series.releaseDate).getFullYear() : null;

    // Handle play functionality
    const handlePlay = useCallback(() => {
        // Get first episode of the first season
        const seasonNumbers = Object.keys(series.seasonsWithEpisodes)
            .map(Number)
            .sort((a, b) => a - b);

        if (seasonNumbers.length > 0) {
            const firstSeason = seasonNumbers[0];
            const episodes = series.seasonsWithEpisodes[firstSeason];

            if (episodes && episodes.length > 0) {
                // Here you would typically trigger playback of the first episode
                console.log('Playing first episode:', episodes[0]);

                // For demonstration, let's open the trailer instead if there's no actual playback
                if (series.youtubeTrailer) {
                    setIsTrailerOpen(true);
                }
            }
        }
    }, [series]);

    // Handle playing a specific episode
    const handlePlayEpisode = useCallback((episode: SeasonWithEpisodes) => {
        console.log('Playing episode:', episode);
        // Here you would implement actual episode playback
    }, []);

    // Handle trailer button click
    const handleTrailerClick = useCallback(() => {
        setIsTrailerOpen(true);
    }, []);

    const addToWatchlist = useCallback(() => {
        addToWatchlistCall(route('series.watchlist', { model: num }), {
            preserveScroll: true,
            preserveState: true,
        });
    }, [addToWatchlistCall, num]);

    const removeFromWatchlist = useCallback(() => {
        removeFromWatchlistCall(route('series.watchlist', { model: num }), {
            preserveScroll: true,
            preserveState: true,
        });
    }, [removeFromWatchlistCall, num]);

    // Define breadcrumbs for navigation
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Series',
            href: '/series',
        },
        {
            title: series.name,
            href: `/series/${series.seriesId}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${series.name} | Series Information`} />

            <ErrorBoundary FallbackComponent={ErrorFallback}>
                <div className="relative w-full">
                    {/* Hero Section with fallback image handling */}
                    <MediaHeroSection
                        title={series.name}
                        description={series.plot}
                        releaseYear={releaseYear ?? ''}
                        rating={series.rating_5based}
                        duration={series.episodeRunTime}
                        genres={series.genre}
                        backdropUrl={series.backdropPath?.length > 0 ? series.backdropPath[0] : null}
                        posterUrl={series.cover}
                        additionalBackdrops={series.backdropPath?.slice(1) || []}
                        trailerUrl={series.youtubeTrailer}
                        onPlay={handlePlay}
                        onTrailerPlay={handleTrailerClick}
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
                                        seasonsWithEpisodes={series.seasonsWithEpisodes}
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
                                    <CastList cast={series.cast} director={series.director} />
                                </motion.div>
                            </AnimatePresence>
                        </div>
                    </div>

                    {/* Trailer Video Modal */}
                    <VideoTrailerPreview
                        trailerUrl={series.youtubeTrailer}
                        isOpen={isTrailerOpen}
                        onClose={() => setIsTrailerOpen(false)}
                    />
                </div>
            </ErrorBoundary>
        </AppLayout>
    );
}
