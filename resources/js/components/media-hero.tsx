import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { motion } from 'framer-motion';
import { BookmarkIcon, HeartIcon, PlayIcon, PlusIcon } from 'lucide-react';
import { useState } from 'react';

interface MediaHeroProps {
    title: string;
    backdropUrl?: string;
    posterUrl?: string;
    releaseYear?: string;
    rating?: string | number;
    runtime?: string;
    genres?: string | string[];
    plot?: string;
    className?: string;
    onPlay?: () => void;
    trailerUrl?: string;
}

export default function MediaHero({
    title,
    backdropUrl,
    releaseYear,
    rating,
    runtime,
    genres,
    plot,
    className,
    onPlay,
}: MediaHeroProps) {
    const [isInMyList, setIsInMyList] = useState(false);
    const [isFavorite, setIsFavorite] = useState(false);
    const [isExpanded, setIsExpanded] = useState(false);

    // Format genres string into array if needed
    const genreArray = Array.isArray(genres)
        ? genres
        : genres
              ?.split(',')
              .map((g) => g.trim())
              .filter(Boolean) || [];

    // Truncate plot if it's too long
    const truncatedPlot = plot && plot.length > 200 && !isExpanded ? plot.substring(0, 200) + '...' : plot;

    return (
        <div className={cn('relative w-full overflow-hidden', className)}>
            {/* Backdrop Image with Gradient Overlay */}
            <div className="relative h-[70vh] w-full overflow-hidden md:h-[80vh]">
                {backdropUrl && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        transition={{ duration: 1 }}
                        className="absolute inset-0 z-0"
                    >
                        <img src={backdropUrl} alt={title} className="h-full w-full object-cover object-center" />
                        <div className="from-background via-background/80 to-background/10 absolute inset-0 bg-gradient-to-t" />
                        <div className="from-background/90 via-background/50 absolute inset-0 bg-gradient-to-r to-transparent" />
                    </motion.div>
                )}

                {/* Content Container */}
                <div className="relative z-10 flex h-full flex-col justify-end p-6 md:p-12">
                    <div className="max-w-3xl">
                        {/* Title with Animation */}
                        <motion.h1
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6 }}
                            className="mb-3 text-4xl font-bold tracking-tight md:text-5xl lg:text-6xl"
                        >
                            {title}
                        </motion.h1>

                        {/* Metadata */}
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
                            {runtime && <span>{runtime}</span>}
                        </motion.div>

                        {/* Genre tags */}
                        {genreArray.length > 0 && (
                            <motion.div
                                initial={{ opacity: 0 }}
                                animate={{ opacity: 1 }}
                                transition={{ duration: 0.6, delay: 0.3 }}
                                className="mb-4 flex flex-wrap gap-2"
                            >
                                {genreArray.map((genre, index) => (
                                    <Badge key={index} variant="outline" className="bg-background/50 backdrop-blur-sm">
                                        {genre}
                                    </Badge>
                                ))}
                            </motion.div>
                        )}

                        {/* Plot */}
                        {plot && (
                            <motion.div
                                initial={{ opacity: 0 }}
                                animate={{ opacity: 1 }}
                                transition={{ duration: 0.6, delay: 0.4 }}
                            >
                                <p className="mb-6 text-sm leading-relaxed md:text-base">
                                    {truncatedPlot}
                                    {plot.length > 200 && (
                                        <button
                                            onClick={() => setIsExpanded(!isExpanded)}
                                            className="text-primary ml-2 font-medium hover:underline focus:outline-none"
                                        >
                                            {isExpanded ? 'Show less' : 'Show more'}
                                        </button>
                                    )}
                                </p>
                            </motion.div>
                        )}

                        {/* Buttons */}
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6, delay: 0.5 }}
                            className="flex flex-wrap gap-3"
                        >
                            <Button
                                size="lg"
                                onClick={onPlay}
                                className="bg-primary text-primary-foreground hover:bg-primary/90 gap-2"
                            >
                                <PlayIcon size={18} />
                                Play
                            </Button>

                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            size="lg"
                                            variant="secondary"
                                            onClick={() => setIsInMyList(!isInMyList)}
                                            className="gap-2"
                                        >
                                            {isInMyList ? <BookmarkIcon size={18} /> : <PlusIcon size={18} />}
                                            {isInMyList ? 'In My List' : 'My List'}
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{isInMyList ? 'Remove from My List' : 'Add to My List'}</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>

                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            size="icon"
                                            variant="secondary"
                                            onClick={() => setIsFavorite(!isFavorite)}
                                            className="h-10 w-10"
                                        >
                                            <HeartIcon
                                                size={18}
                                                className={cn(
                                                    'transition-colors',
                                                    isFavorite ? 'fill-red-500 text-red-500' : '',
                                                )}
                                            />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{isFavorite ? 'Remove from Favorites' : 'Add to Favorites'}</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </motion.div>
                    </div>
                </div>
            </div>
        </div>
    );
}
