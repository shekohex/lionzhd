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
    const nextPageUrl = links?.find(link => link.label === 'Next »' || link.label.includes('Next'))?.url;
    const hasMore = !!nextPageUrl;
    
    // Reset accumulated data when base data changes (e.g., filters applied)
    useEffect(() => {
        if (isInitialLoad.current) {
            isInitialLoad.current = false;
            return;
        }
        
        // If we got new data and it's a different dataset (not an append), reset
        const currentPageFromUrl = new URL(window.location.href).searchParams.get('page');
        const pageNum = currentPageFromUrl ? parseInt(currentPageFromUrl, 10) : 1;
        
        // Reset if we're back to page 1 or if the data length suggests a fresh start
        if (pageNum === 1 || data.length !== allData.length) {
            setAllData(data);
        }
    }, [data, allData.length]);
    
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
                    onSuccess: (page: { props: Record<string, { data?: T[] }> }) => {
                        // Extract the new data from the response
                        const newItems = only?.[0] ? page.props[only[0]]?.data : data;
                        
                        if (newItems && Array.isArray(newItems)) {
                            setAllData(prev => [...prev, ...newItems]);
                        }
                        
                        resolve();
                    },
                    onError: (errors: Record<string, string[]>) => {
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