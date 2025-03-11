import { cn } from '@/lib/utils';
import { motion } from 'framer-motion';
import React, { useCallback, useEffect, useState } from 'react';
import { useErrorBoundary } from 'react-error-boundary';
import ResponsiveImage, { ImageLoadingState } from './responsive-image';
import { Skeleton } from './ui/skeleton';

export interface MediaBackdropProps {
    // Main backdrop image URL - will be tried first
    backdropUrl?: string | null;
    // Fallback to poster/cover image if backdrop fails
    posterUrl?: string | null;
    // Additional fallback images if available
    additionalBackdrops?: string[];
    // Title for accessibility
    title?: string;
    // Whether to apply gradient overlay
    withGradient?: boolean;
    // Classname for customization
    className?: string;
    // Optional height for the backdrop
    heightClass?: string;
    // True to apply dynamic background color based on image
    dynamicBgColor?: boolean;
    // Optional overlay color for text readability (default dark gradient)
    overlayClassName?: string;
    // Children to render on top of the backdrop
    children?: React.ReactNode;
    // Error handling options
    errorRetryCount?: number;
    // Whether to show loading state
    showLoadingState?: boolean;
}

const MediaBackdrop: React.FC<MediaBackdropProps> = ({
    backdropUrl,
    posterUrl,
    additionalBackdrops = [],
    title = 'Media backdrop',
    withGradient = true,
    className,
    heightClass = 'h-[70vh]',
    dynamicBgColor = false,
    overlayClassName,
    children,
    errorRetryCount = 2,
    showLoadingState = true,
}) => {
    // States for tracking loading and errors
    const [loadingState, setLoadingState] = useState<ImageLoadingState>('loading');
    const [retryCount, setRetryCount] = useState(0);
    const [dominantColor, setDominantColor] = useState('rgba(0, 0, 0, 0.5)');

    // Access error boundary context
    const { showBoundary } = useErrorBoundary();

    // Handle loading state changes
    const handleLoaded = useCallback(() => {
        setLoadingState('loaded');
    }, []);

    // Handle load failures - retry or show placeholder
    const handleAllFailed = useCallback(() => {
        if (retryCount < errorRetryCount) {
            // Retry loading with a small delay
            setTimeout(() => {
                setRetryCount((prev) => prev + 1);
                setLoadingState('loading');
            }, 500);
        } else {
            setLoadingState('error');
        }
    }, [retryCount, errorRetryCount]);

    // Extract dominant color from image when loaded
    // This is a simplified implementation - a real color extraction would use canvas
    useEffect(() => {
        if (dynamicBgColor && loadingState === 'loaded' && backdropUrl) {
            // In a real implementation, we would extract the dominant color
            // For demonstration, we set a placeholder color
            setDominantColor('rgba(20, 20, 30, 0.8)');

            // Example of how you would implement color extraction:
            // 1. Create an off-screen canvas
            // 2. Draw the image
            // 3. Get image data and calculate the dominant color
            // 4. Set the dominant color state
        }
    }, [dynamicBgColor, loadingState, backdropUrl]);

    // Set error in the boundary for critical failures
    useEffect(() => {
        if (loadingState === 'error' && retryCount >= errorRetryCount) {
            // Instead of crashing the UI, we'll just log it
            console.error('Failed to load media backdrop after multiple attempts');
        }
    }, [loadingState, retryCount, errorRetryCount, showBoundary]);

    return (
        <div
            className={cn('relative w-full overflow-hidden', heightClass, className)}
            style={dynamicBgColor && loadingState === 'loaded' ? { backgroundColor: dominantColor } : {}}
        >
            {/* Main backdrop image with fallbacks */}
            <motion.div
                initial={{ opacity: 0 }}
                animate={{
                    opacity: loadingState === 'loaded' ? 1 : 0,
                }}
                transition={{ duration: 0.5 }}
                className="absolute inset-0 z-0"
            >
                <ResponsiveImage
                    src={backdropUrl || undefined}
                    fallbackSrc={posterUrl || undefined}
                    additionalFallbacks={additionalBackdrops}
                    alt={`${title} backdrop`}
                    className="h-full w-full"
                    aspectRatio=""
                    showSkeleton={false}
                    showPlaceholder={true}
                    placeholderClassName="h-full w-full bg-gradient-to-b from-muted/20 to-muted"
                    onLoaded={handleLoaded}
                    onAllFailed={handleAllFailed}
                />

                {/* Gradient overlay for text readability */}
                {withGradient && loadingState === 'loaded' && (
                    <>
                        <div
                            className={cn(
                                'from-background via-background/80 to-background/10 absolute inset-0 bg-gradient-to-t',
                                overlayClassName,
                            )}
                        />
                        <div className="from-background/90 via-background/50 absolute inset-0 bg-gradient-to-r to-transparent" />
                    </>
                )}
            </motion.div>

            {/* Loading state */}
            {showLoadingState && loadingState === 'loading' && (
                <div className="absolute inset-0 z-10 flex items-center justify-center">
                    <Skeleton className="h-full w-full" />
                </div>
            )}

            {/* Content overlay */}
            {children && <div className="relative z-10 h-full w-full">{children}</div>}
        </div>
    );
};

export default MediaBackdrop;
