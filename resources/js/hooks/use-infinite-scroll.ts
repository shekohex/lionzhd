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

    // Find next page URL from links
    const nextPageUrl = links?.find((link) => link.label === 'Next »' || link.label.includes('Next'))?.url;
    const hasMore = !!nextPageUrl;

    // Reset accumulated data when base data changes (e.g., filters applied)
    useEffect(() => {
        if (isInitialLoad.current) {
            isInitialLoad.current = false;
            return;
        }

        // Determine current page from pagination metadata or URL
        const currentPage =
            links?.reduce<number | null>((page, link) => {
                if (!link.active) {
                    return page;
                }

                const parsed = parseInt(link.label, 10);
                return Number.isNaN(parsed) ? page : parsed;
            }, null) ??
            (() => {
                if (typeof window === 'undefined') {
                    return 1;
                }
                const params = new URL(window.location.href).searchParams;
                const pageParam = params.get('page');
                const parsed = pageParam ? parseInt(pageParam, 10) : 1;
                return Number.isNaN(parsed) ? 1 : parsed;
            })();

        // When we're back on the first page (e.g., filters/search applied), reset accumulated data
        if (currentPage === 1) {
            setAllData(data);
        }
    }, [data, links]);

    const loadMore = useCallback(async () => {
        if (!nextPageUrl || isLoading || !enabled) {
            return;
        }

        setIsLoading(true);
        setError(null);

        try {
            await new Promise<void>((resolve, reject) => {
                router.visit(nextPageUrl, {
                    method: 'get',
                    preserveState,
                    preserveScroll,
                    only,
                    onSuccess: (page) => {
                        const props = page.props as Record<string, { data?: T[] }>;
                        // Extract the new data from the response
                        const newItems = only?.[0] ? props[only[0]]?.data : data;

                        if (newItems && Array.isArray(newItems)) {
                            setAllData((prev) => [...prev, ...newItems]);
                        }

                        resolve();
                    },
                    onError: (errors) => {
                        console.error('Error loading more items:', errors);
                        setError('Failed to load more items. Please try again.');
                        reject(new Error('Failed to load more items'));
                    },
                    onFinish: () => {
                        setIsLoading(false);
                    },
                });
            });
        } catch (err) {
            setIsLoading(false);
            console.error('Error in loadMore:', err);
        }
    }, [nextPageUrl, isLoading, enabled, preserveState, preserveScroll, only, data]);

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
