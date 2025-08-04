import { Button } from '@/components/ui/button';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';
import { scrollToTop } from '@/lib/scroll-utils';
import { type Link as PaginationLink } from '@/types/pagination';
import { ChevronDownIcon, LoaderIcon } from 'lucide-react';
import { ComponentProps } from 'react';
import { Pagination as BasePagination } from './pagination';

interface EnhancedPaginationProps {
    links: PaginationLink[];
    className?: string;
    preserveScroll?: boolean;
    preserveState?: boolean;
    prefetch?: boolean;
    only?: string[];
    /**
     * Position of this pagination component
     */
    position?: 'top' | 'bottom';
    /**
     * Whether to scroll to top when pagination is clicked
     */
    scrollToTopOnClick?: boolean;
    /**
     * Infinite scroll props for mobile
     */
    infiniteScroll?: {
        isLoading: boolean;
        hasMore: boolean;
        error: string | null;
        loadMore: () => void;
        clearError: () => void;
    };
    /**
     * Whether to show the load more button on mobile instead of auto-loading
     */
    showLoadMoreButton?: boolean;
}

/**
 * Loading indicator for infinite scroll
 */
function InfiniteScrollLoader({ 
    isLoading, 
    hasMore, 
    error, 
    onLoadMore, 
    onClearError,
    showLoadMoreButton = false
}: {
    isLoading: boolean;
    hasMore: boolean;
    error: string | null;
    onLoadMore: () => void;
    onClearError: () => void;
    showLoadMoreButton?: boolean;
}) {
    if (error) {
        return (
            <div className="flex flex-col items-center gap-4 py-8">
                <p className="text-sm text-muted-foreground text-center">
                    {error}
                </p>
                <Button 
                    variant="outline" 
                    size="sm" 
                    onClick={onClearError}
                    className="px-6"
                >
                    Try Again
                </Button>
            </div>
        );
    }

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-8">
                <LoaderIcon className="h-6 w-6 animate-spin text-muted-foreground" />
                <span className="ml-2 text-sm text-muted-foreground">Loading more...</span>
            </div>
        );
    }

    if (!hasMore) {
        return (
            <div className="py-8 text-center">
                <p className="text-sm text-muted-foreground">
                    You've reached the end!
                </p>
            </div>
        );
    }

    if (showLoadMoreButton) {
        return (
            <div className="flex justify-center py-8">
                <Button 
                    variant="outline" 
                    onClick={onLoadMore}
                    className="px-8"
                >
                    <ChevronDownIcon className="h-4 w-4 mr-2" />
                    Load More
                </Button>
            </div>
        );
    }

    // For auto-loading, show a subtle indicator when near bottom
    return (
        <div className="flex justify-center py-4">
            <div className="text-xs text-muted-foreground">
                Scroll down for more...
            </div>
        </div>
    );
}

/**
 * Enhanced pagination component that supports both traditional pagination
 * and infinite scroll based on device type and configuration
 */
export function EnhancedPagination({
    links,
    className,
    preserveScroll = true,
    preserveState = true,
    prefetch = false,
    only = [],
    position = 'bottom',
    scrollToTopOnClick = false,
    infiniteScroll,
    showLoadMoreButton = false,
    ...props
}: EnhancedPaginationProps & ComponentProps<'div'>) {
    const isMobile = useIsMobile();

    // Handle pagination click with optional scroll to top
    const handlePaginationClick = () => {
        if (scrollToTopOnClick && position === 'bottom') {
            // Small delay to allow Inertia navigation to start
            setTimeout(() => {
                scrollToTop('smooth');
            }, 100);
        }
    };

    // On mobile, show infinite scroll instead of pagination
    if (isMobile && infiniteScroll) {
        return (
            <div className={cn('w-full', className)} {...props}>
                <InfiniteScrollLoader
                    isLoading={infiniteScroll.isLoading}
                    hasMore={infiniteScroll.hasMore}
                    error={infiniteScroll.error}
                    onLoadMore={infiniteScroll.loadMore}
                    onClearError={infiniteScroll.clearError}
                    showLoadMoreButton={showLoadMoreButton}
                />
            </div>
        );
    }

    // On desktop or when infinite scroll is disabled, show traditional pagination
    if (links.length <= 3) {
        return null;
    }

    return (
        <div 
            className={cn('w-full', className)} 
            onClick={handlePaginationClick}
            {...props}
        >
            <BasePagination
                links={links}
                preserveScroll={preserveScroll}
                preserveState={preserveState}
                prefetch={prefetch}
                only={only}
            />
        </div>
    );
}

/**
 * Wrapper component for dual pagination (top and bottom)
 */
export function DualPagination({
    links,
    topClassName,
    bottomClassName,
    showTop = true,
    showBottom = true,
    ...sharedProps
}: EnhancedPaginationProps & {
    topClassName?: string;
    bottomClassName?: string;
    showTop?: boolean;
    showBottom?: boolean;
}) {
    const isMobile = useIsMobile();

    // On mobile with infinite scroll, only show the bottom loader
    if (isMobile && sharedProps.infiniteScroll) {
        return showBottom ? (
            <EnhancedPagination
                {...sharedProps}
                links={links}
                position="bottom"
                className={bottomClassName}
            />
        ) : null;
    }

    return (
        <>
            {showTop && (
                <EnhancedPagination
                    {...sharedProps}
                    links={links}
                    position="top"
                    scrollToTopOnClick={false}
                    className={topClassName}
                />
            )}
            {showBottom && (
                <EnhancedPagination
                    {...sharedProps}
                    links={links}
                    position="bottom"
                    scrollToTopOnClick={true}
                    className={bottomClassName}
                />
            )}
        </>
    );
}