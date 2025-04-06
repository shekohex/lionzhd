import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { ImageIcon } from 'lucide-react';
import React, { useCallback, useEffect, useState } from 'react';

export type ImageLoadingState = 'loading' | 'loaded' | 'error';

export interface ResponsiveImageProps extends React.ImgHTMLAttributes<HTMLImageElement> {
    // Primary image source
    src?: string;
    // Fallback image source to use if primary fails
    fallbackSrc?: string;
    // Additional fallback image sources in order of preference
    additionalFallbacks?: string[];
    // Whether to show a placeholder when all images fail
    showPlaceholder?: boolean;
    // Optional class for the placeholder
    placeholderClassName?: string;
    // Optional aspect ratio for placeholder (e.g., 'aspect-video', 'aspect-square')
    aspectRatio?: string;
    // Optional blur effect while loading
    blurEffect?: boolean;
    // Whether to show loading skeleton
    showSkeleton?: boolean;
    // Called when the image successfully loads
    onLoaded?: () => void;
    // Called when all image sources fail
    onAllFailed?: () => void;
}

const ResponsiveImage: React.FC<ResponsiveImageProps> = ({
    src,
    fallbackSrc,
    additionalFallbacks = [],
    alt = 'Image',
    className,
    showPlaceholder = true,
    placeholderClassName,
    aspectRatio = 'aspect-video',
    blurEffect = true,
    showSkeleton = true,
    onLoaded,
    onAllFailed,
    ...props
}) => {
    // Combine all possible sources in priority order
    const allPossibleSources = React.useMemo(
        () => [src, fallbackSrc, ...additionalFallbacks].filter(Boolean) as string[],
        [src, fallbackSrc, additionalFallbacks],
    );

    // Track the current attempt index
    const [currentAttemptIndex, setCurrentAttemptIndex] = useState<number>(0);

    // Track loading state
    const [loadingState, setLoadingState] = useState<ImageLoadingState>('loading');

    // Current source based on the attempt index
    const currentSrc = allPossibleSources[currentAttemptIndex];

    // Handler for successful image loads
    const handleImageLoaded = useCallback(() => {
        setLoadingState('loaded');
        onLoaded?.();
    }, [onLoaded]);

    // Handler for image load errors
    const handleImageError = useCallback(() => {
        console.error(`Failed to load image: ${currentSrc}`);

        // Try the next source
        const nextIndex = currentAttemptIndex + 1;
        if (nextIndex < allPossibleSources.length) {
            console.log(
                `Trying next source (${nextIndex + 1}/${allPossibleSources.length}): ${allPossibleSources[nextIndex]}`,
            );
            setCurrentAttemptIndex(nextIndex);
            setLoadingState('loading');
        } else {
            // All sources have been tried and failed
            console.error(`All image sources failed (${allPossibleSources.length} attempted)`);
            setLoadingState('error');
            onAllFailed?.();
        }
    }, [currentSrc, allPossibleSources, currentAttemptIndex, onAllFailed]);

    // Reset component state when sources change
    useEffect(() => {
        // Reset to the first source when sources change
        setCurrentAttemptIndex(0);
        setLoadingState('loading');
    }, []);

    // Render image placeholder when in error state and showPlaceholder is true
    if (loadingState === 'error' && showPlaceholder) {
        return <ImagePlaceholder className={cn(aspectRatio, className, placeholderClassName)} alt={alt} />;
    }

    return (
        <div className={cn('relative overflow-hidden', aspectRatio, className)}>
            {/* Show skeleton while loading */}
            {loadingState === 'loading' && showSkeleton && <Skeleton className="absolute inset-0 z-10" />}
            {currentSrc && (
                <img
                    src={currentSrc}
                    alt={alt}
                    className={cn(
                        'h-full w-full object-cover transition-opacity duration-300',
                        loadingState === 'loading' && 'opacity-0',
                        loadingState === 'loaded' && 'opacity-100',
                        blurEffect && loadingState === 'loading' && 'blur-sm',
                    )}
                    onLoad={handleImageLoaded}
                    onError={handleImageError}
                    {...props}
                />
            )}
            {/* Show placeholder if no current src */}
            {!currentSrc && showPlaceholder && (
                <ImagePlaceholder className={cn('absolute inset-0', placeholderClassName)} alt={alt} />
            )}
        </div>
    );
};

// Placeholder component for when images fail to load
interface ImagePlaceholderProps {
    className?: string;
    alt?: string;
}

export const ImagePlaceholder: React.FC<ImagePlaceholderProps> = ({ className, alt = 'Image unavailable' }) => {
    return (
        <div
            className={cn('bg-muted text-muted-foreground flex flex-col items-center justify-center', className)}
            role="img"
            aria-label={alt}
        >
            <ImageIcon className="h-8 w-8 opacity-50" />
            <span className="mt-2 text-xs">Image unavailable</span>
        </div>
    );
};

export default ResponsiveImage;
