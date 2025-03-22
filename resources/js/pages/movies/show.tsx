import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { MovieInformationPageProps } from '@/types/movies';
import { Head, useForm, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { useCallback, useState } from 'react';
import { ErrorBoundary, FallbackProps } from 'react-error-boundary';

// Custom components
import CastList from '@/components/cast-list';
import MediaHeroSection from '@/components/media-hero-section';
import { Badge } from '@/components/ui/badge';
import VideoTrailerPreview from '@/components/video-trailer-preview';
import { Film } from 'lucide-react';

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

export default function MovieInformation() {
    const { props } = usePage<MovieInformationPageProps>();
    const { post: addToWatchlistCall, delete: removeFromWatchlistCall } = useForm();
    const { delete: forgetCache } = useForm();
    const { info, in_watchlist } = props;

    // State for trailer modal
    const [isTrailerOpen, setIsTrailerOpen] = useState(false);

    // Get release year from full date
    const releaseYear = info.releaseDate ? new Date(info.releaseDate).getFullYear() : null;

    // Handle movie playback
    const handlePlay = useCallback(() => {
        // For demonstration purposes, this would be connected to actual video playback
        console.log('Playing movie:', info.movie.name);

        // Without actual implementation, just open the trailer if available
        if (info.youtubeTrailer) {
            setIsTrailerOpen(true);
        }
    }, [info]);

    // Handle trailer button click
    const handleTrailerClick = useCallback(() => {
        setIsTrailerOpen(true);
    }, []);

    const addToWatchlist = useCallback(() => {
        addToWatchlistCall(route('movies.watchlist', { model: info.vodId }), {
            preserveScroll: true,
            preserveState: false,
        });
    }, [addToWatchlistCall, info.vodId]);

    const removeFromWatchlist = useCallback(() => {
        removeFromWatchlistCall(route('movies.watchlist', { model: info.vodId }), {
            preserveScroll: true,
            preserveState: false,
        });
    }, [removeFromWatchlistCall, info.vodId]);

    const handleForgetCache = useCallback(() => {
        forgetCache(route('movies.cache', { model: info.vodId }), { preserveScroll: true, preserveState: false });
    }, [forgetCache, info.vodId]);

    // Technical details section with video/audio information
    const renderTechnicalDetails = () => {
        return (
            <div className="border-border bg-card mt-8 rounded-lg border p-6">
                <h3 className="mb-4 text-lg font-medium">Technical Details</h3>

                <div className="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                    <div>
                        <h4 className="text-muted-foreground mb-2 font-medium">Video</h4>
                        <dl className="space-y-1">
                            {info.video?.codecName && (
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">Codec:</dt>
                                    <dd>{info.video.codecName.toUpperCase()}</dd>
                                </div>
                            )}
                            {info.video?.width && info.video?.height && (
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">Resolution:</dt>
                                    <dd>
                                        {info.video.width}×{info.video.height}
                                    </dd>
                                </div>
                            )}
                            {info.video?.bitRate && (
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">Bitrate:</dt>
                                    <dd>{Math.round(parseInt(info.video.bitRate) / 1000)} Kbps</dd>
                                </div>
                            )}
                        </dl>
                    </div>

                    <div>
                        <h4 className="text-muted-foreground mb-2 font-medium">Audio</h4>
                        <dl className="space-y-1">
                            {info.audio?.codecName && (
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">Codec:</dt>
                                    <dd>{info.audio.codecName.toUpperCase()}</dd>
                                </div>
                            )}
                            {info.audio?.channels && (
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">Channels:</dt>
                                    <dd>
                                        {info.audio.channelLayout
                                            ? info.audio.channelLayout.toUpperCase()
                                            : `${info.audio.channels} channels`}
                                    </dd>
                                </div>
                            )}
                            {info.audio?.bitRate && (
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">Bitrate:</dt>
                                    <dd>{Math.round(parseInt(info.audio.bitRate) / 1000)} Kbps</dd>
                                </div>
                            )}
                        </dl>
                    </div>
                </div>
            </div>
        );
    };

    // Define breadcrumbs for navigation
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Movies',
            href: route('movies'),
        },
        {
            title: info.movie.name,
            href: route('movies.show', { model: info.vodId }),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${info.movie.name} | Movie Information`} />

            <ErrorBoundary FallbackComponent={ErrorFallback}>
                <div className="relative w-full">
                    {/* Hero Section with fallback image handling */}
                    <MediaHeroSection
                        title={info.movie.name}
                        description={info.plot}
                        releaseYear={releaseYear ?? '20??'}
                        rating={info.rating}
                        duration={info.duration}
                        genres={info.genre}
                        backdropUrl={info.backdropPath?.length > 0 ? info.backdropPath[0] : null}
                        posterUrl={info.movieImage || info.backdrop}
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
                            {/* Cast & Crew Section */}
                            <AnimatePresence>
                                <motion.div
                                    initial={{ opacity: 0, y: 20 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.6 }}
                                >
                                    <CastList cast={info.cast} director={info.director} />
                                </motion.div>
                            </AnimatePresence>

                            {/* Movie Details Section */}
                            <AnimatePresence>
                                <motion.div
                                    initial={{ opacity: 0, y: 20 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.6, delay: 0.2 }}
                                >
                                    <div className="flex flex-col gap-6">
                                        <h2 className="text-xl font-semibold">Movie Details</h2>

                                        {/* File details */}
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge variant="outline" className="bg-card/50 backdrop-blur-sm">
                                                <Film className="mr-1 h-3 w-3" />{' '}
                                                {info.movie.containerExtension.toUpperCase()}
                                            </Badge>

                                            {info.bitrate && (
                                                <Badge variant="outline" className="bg-card/50 backdrop-blur-sm">
                                                    {Math.round(info.bitrate / 1000)} Kbps
                                                </Badge>
                                            )}

                                            {info.video?.width && info.video?.height && (
                                                <Badge variant="outline" className="bg-card/50 backdrop-blur-sm">
                                                    {info.video.width}×{info.video.height}
                                                </Badge>
                                            )}
                                        </div>

                                        {/* Technical details */}
                                        {renderTechnicalDetails()}
                                    </div>
                                </motion.div>
                            </AnimatePresence>
                        </div>
                    </div>

                    {/* Trailer Video Modal */}
                    {info.youtubeTrailer && (
                        <VideoTrailerPreview
                            trailerUrl={info.youtubeTrailer}
                            isOpen={isTrailerOpen}
                            onClose={() => setIsTrailerOpen(false)}
                        />
                    )}
                </div>
            </ErrorBoundary>
        </AppLayout>
    );
}
