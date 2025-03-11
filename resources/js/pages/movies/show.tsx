import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { MovieInformationPageProps } from '@/types/movies';
import { Head, usePage } from '@inertiajs/react';
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
    const { movie } = props;

    // State for trailer modal
    const [isTrailerOpen, setIsTrailerOpen] = useState(false);

    // Get release year from full date
    const releaseYear = movie.releaseDate ? new Date(movie.releaseDate).getFullYear() : null;

    // Handle movie playback
    const handlePlay = useCallback(() => {
        // For demonstration purposes, this would be connected to actual video playback
        console.log('Playing movie:', movie.movie.name);

        // Without actual implementation, just open the trailer if available
        if (movie.youtubeTrailer) {
            setIsTrailerOpen(true);
        }
    }, [movie]);

    // Handle trailer button click
    const handleTrailerClick = useCallback(() => {
        setIsTrailerOpen(true);
    }, []);

    // Technical details section with video/audio information
    const renderTechnicalDetails = () => {
        return (
            <div className="border-border bg-card mt-8 rounded-lg border p-6">
                <h3 className="mb-4 text-lg font-medium">Technical Details</h3>

                <div className="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                    <div>
                        <h4 className="text-muted-foreground mb-2 font-medium">Video</h4>
                        <dl className="space-y-1">
                            {movie.video?.codecName && (
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">Codec:</dt>
                                    <dd>{movie.video.codecName.toUpperCase()}</dd>
                                </div>
                            )}
                            {movie.video?.width && movie.video?.height && (
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">Resolution:</dt>
                                    <dd>
                                        {movie.video.width}×{movie.video.height}
                                    </dd>
                                </div>
                            )}
                            {movie.video?.bitRate && (
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">Bitrate:</dt>
                                    <dd>{Math.round(parseInt(movie.video.bitRate) / 1000)} Kbps</dd>
                                </div>
                            )}
                        </dl>
                    </div>

                    <div>
                        <h4 className="text-muted-foreground mb-2 font-medium">Audio</h4>
                        <dl className="space-y-1">
                            {movie.audio?.codecName && (
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">Codec:</dt>
                                    <dd>{movie.audio.codecName.toUpperCase()}</dd>
                                </div>
                            )}
                            {movie.audio?.channels && (
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">Channels:</dt>
                                    <dd>
                                        {movie.audio.channelLayout
                                            ? movie.audio.channelLayout.toUpperCase()
                                            : `${movie.audio.channels} channels`}
                                    </dd>
                                </div>
                            )}
                            {movie.audio?.bitRate && (
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">Bitrate:</dt>
                                    <dd>{Math.round(parseInt(movie.audio.bitRate) / 1000)} Kbps</dd>
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
            title: movie.movie.name,
            href: route('movies.show', { model: movie.vodId }),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${movie.movie.name} | Movie Information`} />

            <ErrorBoundary FallbackComponent={ErrorFallback}>
                <div className="relative w-full">
                    {/* Hero Section with fallback image handling */}
                    <MediaHeroSection
                        title={movie.movie.name}
                        description={movie.plot}
                        releaseYear={releaseYear ?? '20??'}
                        rating={movie.rating}
                        duration={movie.duration}
                        genres={movie.genre}
                        backdropUrl={movie.backdropPath?.length > 0 ? movie.backdropPath[0] : null}
                        posterUrl={movie.movieImage || movie.backdrop}
                        additionalBackdrops={movie.backdropPath?.slice(1) || []}
                        trailerUrl={movie.youtubeTrailer}
                        onPlay={handlePlay}
                        onTrailerPlay={handleTrailerClick}
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
                                    <CastList cast={movie.cast} director={movie.director} />
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
                                                {movie.movie.containerExtension.toUpperCase()}
                                            </Badge>

                                            {movie.bitrate && (
                                                <Badge variant="outline" className="bg-card/50 backdrop-blur-sm">
                                                    {Math.round(movie.bitrate / 1000)} Kbps
                                                </Badge>
                                            )}

                                            {movie.video?.width && movie.video?.height && (
                                                <Badge variant="outline" className="bg-card/50 backdrop-blur-sm">
                                                    {movie.video.width}×{movie.video.height}
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
                    {movie.youtubeTrailer && (
                        <VideoTrailerPreview
                            trailerUrl={movie.youtubeTrailer}
                            isOpen={isTrailerOpen}
                            onClose={() => setIsTrailerOpen(false)}
                        />
                    )}
                </div>
            </ErrorBoundary>
        </AppLayout>
    );
}
