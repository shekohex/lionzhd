import ForgetCacheButton from '@/components/forget-cache-button';
import MediaBackdrop from '@/components/media-backdrop';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import WatchlistButton from '@/components/watchlist-button';
import { cn } from '@/lib/utils';
import { motion } from 'framer-motion';
import { Clapperboard, DownloadIcon, ExternalLinkIcon, InfoIcon, PlayIcon } from 'lucide-react';
import React, { useState } from 'react';
import { ErrorBoundary } from 'react-error-boundary';
import { useIsMobile } from '@/hooks/use-mobile';

export interface MediaHeroSectionProps {
    inMyWatchlist?: boolean;
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
    onAddToWatchlist?: () => void;
    onRemoveFromWatchlist?: () => void;
    onForgetCache?: () => void;
    onDownload?: (...args: unknown[]) => void;
    onDirectDownload?: (...args: unknown[]) => void;
    showDirectDownload?: boolean;
    // UI Customization
    className?: string;
    showActions?: boolean;
    showMetadata?: boolean;
    maxDescriptionLength?: number;
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
    onForgetCache,
    onDownload,
    onDirectDownload,
    showDirectDownload = false,
    className,
    showActions = true,
    showMetadata = true,
    maxDescriptionLength = 200,
}) => {
    // State for UI interactions
    const [showFullDescription, setShowFullDescription] = useState(false);
    const isMobile = useIsMobile();

    // Process genre data
    const genreList = Array.isArray(genres) ? genres : genres?.split(',').map((g) => g.trim()) || [];

    // Handle description truncation
    const truncatedDescription =
        description && description.length > maxDescriptionLength && !showFullDescription
            ? `${description.substring(0, maxDescriptionLength)}...`
            : description;

    // Opens YouTube trailer in new tab on mobile; otherwise defers to onTrailerPlay
    const openYouTubeIfMobile = (url?: string) => {
        if (!url) return false;
        if (!isMobile) return false;
        const regExp = /^.*(?:youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=)([^#&?]*).*/;
        const match = url.match(regExp);
        const id = match && match[1] && match[1].length === 11 ? match[1] : url.length === 11 ? url : null;
        if (!id) return false;
        const target = `https://youtu.be/${id}`;
        window.open(target, '_blank', 'noopener');
        return true;
    };

    const handleTrailerClick = () => {
        // Attempt to open native YouTube on mobile; else fallback handler
        if (openYouTubeIfMobile(trailerUrl)) return;
        onTrailerPlay?.();
    };

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
                    heightClass="h-[44svh] sm:h-[56svh] md:h-[75vh]"
                    dynamicBgColor={true}
                    showLoadingState={true}
                    errorRetryCount={2}
                >
                    {/* Content overlay with animated entrance */}
                    <div className="flex h-full flex-col justify-end px-4 py-6 sm:px-6 md:px-8 md:py-10">
                        <div className="mx-auto w-full max-w-7xl">
                            <div className="max-w-3xl">
                            {/* Title */}
                            <motion.h1
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6 }}
                                className="mb-2 text-2xl font-bold tracking-tight sm:text-4xl md:text-5xl lg:text-6xl"
                            >
                                {title}
                            </motion.h1>

                            {/* Metadata bar (year, rating, duration) */}
                            {showMetadata && (
                                <motion.div
                                    initial={{ opacity: 0 }}
                                    animate={{ opacity: 1 }}
                                    transition={{ duration: 0.6, delay: 0.2 }}
                                    className="text-muted-foreground mb-3 flex flex-wrap items-center gap-3 text-sm md:text-base"
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
                                    <p className="mb-4 text-sm leading-relaxed sm:mb-6 md:text-base line-clamp-3 sm:line-clamp-none">
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
                                <>
                                <motion.div
                                    initial={{ opacity: 0, y: 20 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.6, delay: 0.5 }}
                                    className="hidden flex-wrap gap-3 sm:flex"
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
                                        {trailerUrl && (
                                            <Button size="lg" variant="secondary" onClick={() => handleTrailerClick()} className="gap-2">
                                                <Clapperboard size={18} />
                                                Trailer
                                            </Button>
                                        )}

                                    {/* Download button - only if onDownload available */}
                                    {onDownload && showDirectDownload ? (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button size="lg" variant="secondary" className="gap-2">
                                                    <DownloadIcon size={18} />
                                                    Download
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent>
                                                <DropdownMenuItem onClick={onDownload}>
                                                    <DownloadIcon className="mr-2 h-4 w-4" />
                                                    Server Download
                                                </DropdownMenuItem>
                                                {onDirectDownload && (
                                                    <DropdownMenuItem onClick={onDirectDownload}>
                                                        <ExternalLinkIcon className="mr-2 h-4 w-4" />
                                                        Direct Download
                                                    </DropdownMenuItem>
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    ) : onDownload ? (
                                        <Button size="lg" variant="secondary" onClick={onDownload} className="gap-2">
                                            <DownloadIcon size={18} />
                                            Download
                                        </Button>
                                    ) : null}

                                    {/* My List button with tooltip */}
                                    <WatchlistButton
                                        onAddToWatchlist={onAddToWatchlist}
                                        onRemoveFromWatchlist={onRemoveFromWatchlist}
                                        isInWatchlist={inMyWatchlist}
                                    />

                                    {/* Forget Cache button - only if forget cache */}
                                    <ForgetCacheButton onForgetCache={onForgetCache} />
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

                                {/* Mobile actions: icons only */}
                                <motion.div
                                    initial={{ opacity: 0, y: 20 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.6, delay: 0.5 }}
                                    className="mt-2 flex items-center gap-2 sm:hidden"
                                >
                                    <Button
                                        aria-label="Play"
                                        size="icon"
                                        onClick={onPlay}
                                        className="bg-primary text-primary-foreground hover:bg-primary/90"
                                    >
                                        <PlayIcon size={18} />
                                    </Button>
                                    {onDownload && (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button aria-label="Download" size="icon" variant="secondary">
                                                    <DownloadIcon size={18} />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="start">
                                                <DropdownMenuItem onClick={onDownload}>
                                                    <DownloadIcon className="mr-2 h-4 w-4" /> Server Download
                                                </DropdownMenuItem>
                                                {onDirectDownload && (
                                                    <DropdownMenuItem onClick={onDirectDownload}>
                                                        <ExternalLinkIcon className="mr-2 h-4 w-4" /> Direct Download
                                                    </DropdownMenuItem>
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    )}
                                    {trailerUrl && (
                                        <Button aria-label="Trailer" size="icon" variant="secondary" onClick={() => handleTrailerClick()}>
                                            <Clapperboard size={18} />
                                        </Button>
                                    )}
                                    <WatchlistButton
                                        variant="outline"
                                        size="icon"
                                        onAddToWatchlist={onAddToWatchlist}
                                        onRemoveFromWatchlist={onRemoveFromWatchlist}
                                        isInWatchlist={inMyWatchlist}
                                    />
                                    <ForgetCacheButton variant="outline" size="icon" onForgetCache={onForgetCache} />
                                </motion.div>
                                </>
                            )}
                            </div>
                        </div>
                    </div>
                </MediaBackdrop>
            </section>
        </ErrorBoundary>
    );
};

export default MediaHeroSection;
