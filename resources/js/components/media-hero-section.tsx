import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { motion } from 'framer-motion';
import { InfoIcon, PlayIcon } from 'lucide-react';
import React, { useState } from 'react';
import { ErrorBoundary } from 'react-error-boundary';
import MediaBackdrop from './media-backdrop';
import WatchlistButton from './watchlist-button';

export interface MediaHeroSectionProps {
    inMyWatchlist?: () => boolean;
    // Media information
    title: string;
    description?: string;
    releaseYear?: string | number;
    rating?: string | number;
    duration?: string;
    genres?: string[] | string;
    // Image sources with fallback priority
    backdropUrl?: string | null;
    posterUrl?: string | null;
    additionalBackdrops?: string[];
    // Trailer or playback related
    trailerUrl?: string;
    // Actions
    onPlay?: () => void;
    onTrailerPlay?: () => void;
    onMoreInfo?: () => void;
    // UI Customization
    className?: string;
    showActions?: boolean;
    showMetadata?: boolean;
    maxDescriptionLength?: number;
    onAddToWatchlist?: () => void;
    onRemoveFromWatchlist?: () => void;
}

const MediaHeroSection: React.FC<MediaHeroSectionProps> = ({
    title,
    description,
    releaseYear,
    rating,
    duration,
    genres,
    backdropUrl,
    posterUrl,
    additionalBackdrops,
    trailerUrl,
    inMyWatchlist,
    onPlay,
    onTrailerPlay,
    onMoreInfo,
    onAddToWatchlist,
    onRemoveFromWatchlist,
    className,
    showActions = true,
    showMetadata = true,
    maxDescriptionLength = 200,
}) => {
    // State for UI interactions
    const [showFullDescription, setShowFullDescription] = useState(false);

    // Process genre data
    const genreList = Array.isArray(genres) ? genres : genres?.split(',').map((g) => g.trim()) || [];

    // Handle description truncation
    const truncatedDescription =
        description && description.length > maxDescriptionLength && !showFullDescription
            ? `${description.substring(0, maxDescriptionLength)}...`
            : description;

    // Error fallback for the entire hero section
    const HeroErrorFallback = () => (
        <div className="from-muted/30 to-muted relative flex h-[40vh] w-full items-center justify-center bg-gradient-to-b">
            <div className="flex flex-col items-center justify-center p-6 text-center">
                <InfoIcon className="text-muted-foreground mb-4 h-10 w-10" />
                <h1 className="mb-2 text-2xl font-bold">{title}</h1>
                <p className="text-muted-foreground">Unable to load media information. Please try again later.</p>
                {showActions && (
                    <Button className="mt-4" onClick={onPlay}>
                        <PlayIcon className="mr-2 h-4 w-4" /> Play Anyway
                    </Button>
                )}
            </div>
        </div>
    );

    return (
        <ErrorBoundary FallbackComponent={HeroErrorFallback}>
            <section className={cn('relative w-full', className)}>
                <MediaBackdrop
                    backdropUrl={backdropUrl}
                    posterUrl={posterUrl}
                    additionalBackdrops={additionalBackdrops}
                    title={title}
                    withGradient={true}
                    heightClass="h-[70vh] md:h-[80vh]"
                    dynamicBgColor={true}
                    showLoadingState={true}
                    errorRetryCount={2}
                >
                    {/* Content overlay with animated entrance */}
                    <div className="flex h-full flex-col justify-end p-6 md:p-12">
                        <div className="max-w-3xl">
                            {/* Title */}
                            <motion.h1
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6 }}
                                className="mb-3 text-4xl font-bold tracking-tight md:text-5xl lg:text-6xl"
                            >
                                {title}
                            </motion.h1>

                            {/* Metadata bar (year, rating, duration) */}
                            {showMetadata && (
                                <motion.div
                                    initial={{ opacity: 0 }}
                                    animate={{ opacity: 1 }}
                                    transition={{ duration: 0.6, delay: 0.2 }}
                                    className="text-muted-foreground mb-4 flex flex-wrap items-center gap-3 text-sm md:text-base"
                                >
                                    {releaseYear && <span>{releaseYear}</span>}
                                    {rating && (
                                        <span className="flex items-center gap-1">
                                            <span className="text-yellow-500">★</span> {rating}
                                        </span>
                                    )}
                                    {duration && <span>{duration}</span>}
                                </motion.div>
                            )}

                            {/* Genres as badges */}
                            {showMetadata && genreList.length > 0 && (
                                <motion.div
                                    initial={{ opacity: 0 }}
                                    animate={{ opacity: 1 }}
                                    transition={{ duration: 0.6, delay: 0.3 }}
                                    className="mb-4 flex flex-wrap gap-2"
                                >
                                    {genreList.map((genre, index) => (
                                        <Badge
                                            key={index}
                                            variant="outline"
                                            className="bg-background/50 backdrop-blur-sm"
                                        >
                                            {genre}
                                        </Badge>
                                    ))}
                                </motion.div>
                            )}

                            {/* Description with show more/less toggle */}
                            {description && (
                                <motion.div
                                    initial={{ opacity: 0 }}
                                    animate={{ opacity: 1 }}
                                    transition={{ duration: 0.6, delay: 0.4 }}
                                >
                                    <p className="mb-6 text-sm leading-relaxed md:text-base">
                                        {truncatedDescription}
                                        {description.length > maxDescriptionLength && (
                                            <button
                                                onClick={() => setShowFullDescription(!showFullDescription)}
                                                className="text-primary ml-2 font-medium hover:underline focus:outline-none"
                                            >
                                                {showFullDescription ? 'Show less' : 'Show more'}
                                            </button>
                                        )}
                                    </p>
                                </motion.div>
                            )}

                            {/* Action buttons */}
                            {showActions && (
                                <motion.div
                                    initial={{ opacity: 0, y: 20 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.6, delay: 0.5 }}
                                    className="flex flex-wrap gap-3"
                                >
                                    {/* Play button */}
                                    <Button
                                        size="lg"
                                        onClick={onPlay}
                                        className="bg-primary text-primary-foreground hover:bg-primary/90 gap-2"
                                    >
                                        <PlayIcon size={18} />
                                        Play
                                    </Button>

                                    {/* Trailer button - only if trailer available */}
                                    {trailerUrl && onTrailerPlay && (
                                        <Button size="lg" variant="secondary" onClick={onTrailerPlay} className="gap-2">
                                            <PlayIcon size={18} />
                                            Trailer
                                        </Button>
                                    )}

                                    {/* My List button with tooltip */}
                                    <WatchlistButton
                                        onAddToWatchlist={onAddToWatchlist}
                                        onRemoveFromWatchlist={onRemoveFromWatchlist}
                                        isInWatchlist={inMyWatchlist}
                                    />
                                    {/* More Info button */}
                                    {onMoreInfo && (
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        size="icon"
                                                        variant="secondary"
                                                        onClick={onMoreInfo}
                                                        className="h-10 w-10"
                                                    >
                                                        <InfoIcon size={18} />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>More Information</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    )}
                                </motion.div>
                            )}
                        </div>
                    </div>
                </MediaBackdrop>
            </section>
        </ErrorBoundary>
    );
};

export default MediaHeroSection;
