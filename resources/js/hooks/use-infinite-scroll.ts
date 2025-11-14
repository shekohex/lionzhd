import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { isNearBottom, onScroll } from '../lib/scroll-utils';

interface UseInfiniteScrollOptions<T = unknown> {
    /**
     * Current page data from Inertia
     */
    data: T[];

    /**
     * Pagination links from Laravel
     */
    links: Array<{ url?: string; label: string; active: boolean }>;

    /**
     * Base URL for pagination (e.g., '/movies', '/series')
     */
    baseUrl?: string;

    /**
     * Distance from bottom to trigger loading (in pixels)
     * @default 200
     */
    threshold?: number;

    /**
     * Whether to preserve state during navigation
     * @default true
     */
    preserveState?: boolean;

    /**
     * Whether to preserve scroll position during navigation
     * @default false (we manage scroll manually)
     */
    preserveScroll?: boolean;

    /**
     * Only reload specific data keys
     * @default ['movies'] or ['series']
     */
    only?: string[];

    /**
     * Whether infinite scroll is enabled
     * @default true
     */
    enabled?: boolean;

    /**
     * Debounce delay for scroll events (ms)
     * @default 100
     */
    scrollDebounce?: number;
}

interface UseInfiniteScrollReturn<T = unknown> {
    /**
     * Whether we're currently loading more items
     */
    isLoading: boolean;

    /**
     * Whether there are more items to load
     */
    hasMore: boolean;

    /**
     * Error message if loading failed
     */
    error: string | null;

    /**
     * Manually trigger loading more items
     */
    loadMore: () => void;

    /**
     * Reset error state
     */
    clearError: () => void;

    /**
     * All accumulated data items
     */
    allData: T[];
}

export function useInfiniteScroll<T = unknown>({
    data,
    links,
    threshold = 200,
    preserveState = true,
    preserveScroll = false,
    only,
    enabled = true,
    scrollDebounce = 100,
}: Omit<UseInfiniteScrollOptions<T>, 'baseUrl'>): UseInfiniteScrollReturn<T> {
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [allData, setAllData] = useState<T[]>(data);
    const isInitialLoad = useRef(true);
    const lastPageRef = useRef<number>(1);

    // Find next page URL from links
    const nextPageUrl = links?.find((link) => link.label === 'Next »' || link.label.includes('Next'))?.url;
    const hasMore = !!nextPageUrl;

    // Get current page number from URL
    const getCurrentPage = (): number => {
        const currentPageFromUrl = new URL(window.location.href).searchParams.get('page');
        return currentPageFromUrl ? parseInt(currentPageFromUrl, 10) : 1;
    };

    // Reset accumulated data when base data changes (e.g., filters applied or back to page 1)
    useEffect(() => {
        if (isInitialLoad.current) {
            isInitialLoad.current = false;
            lastPageRef.current = getCurrentPage();
            setAllData(data);
            return;
        }

        const currentPage = getCurrentPage();

        // Reset if we're back to page 1 (fresh navigation or filter change)
        if (currentPage === 1 && lastPageRef.current !== 1) {
            setAllData(data);
            lastPageRef.current = 1;
        } else {
            lastPageRef.current = currentPage;
        }
    }, [data]);

    const loadMore = useCallback(() => {
        if (!nextPageUrl || isLoading || !enabled) {
            return;
        }

        setIsLoading(true);
        setError(null);

        router.visit(nextPageUrl, {
            method: 'get',
            preserveState,
            preserveScroll,
            only,
            onSuccess: (page) => {
                const props = page.props as Record<string, { data?: T[] }>;
                // Extract the new data from the response
                const newItems = only?.[0] ? props[only[0]]?.data : undefined;

                if (newItems && Array.isArray(newItems) && newItems.length > 0) {
                    setAllData((prev) => {
                        // Prevent duplicate entries by checking IDs
                        const prevIds = new Set(
                            prev.map((item) => {
                                const itemWithId = item as Record<string, unknown>;
                                return itemWithId.stream_id || itemWithId.series_id || itemWithId.id;
                            }),
                        );
                        const uniqueNewItems = newItems.filter((item) => {
                            const itemWithId = item as Record<string, unknown>;
                            return !prevIds.has(itemWithId.stream_id || itemWithId.series_id || itemWithId.id);
                        });
                        return [...prev, ...uniqueNewItems];
                    });
                }
            },
            onError: (errors) => {
                console.error('Error loading more items:', errors);
                setError('Failed to load more items. Please try again.');
            },
            onFinish: () => {
                setIsLoading(false);
            },
        });
    }, [nextPageUrl, isLoading, enabled, preserveState, preserveScroll, only]);

    // Auto-load when scrolling near bottom
    useEffect(() => {
        if (!enabled || !hasMore) {
            return;
        }

        const cleanup = onScroll(() => {
            if (isNearBottom(threshold) && !isLoading && hasMore) {
                loadMore();
            }
        }, scrollDebounce);

        return cleanup;
    }, [enabled, hasMore, isLoading, loadMore, threshold, scrollDebounce]);

    const clearError = useCallback(() => {
        setError(null);
    }, []);

    return {
        isLoading,
        hasMore,
        error,
        loadMore,
        clearError,
        allData,
    };
}
