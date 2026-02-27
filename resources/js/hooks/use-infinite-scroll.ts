import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { isNearBottom, onScroll } from '../lib/scroll-utils';

interface UseInfiniteScrollOptions<T = unknown> {
    data: T[];
    currentPage: number;
    nextPageUrl: string | null;
    rememberKey?: string;
    restoreScroll?: boolean;
    threshold?: number;
    preserveState?: boolean;
    preserveScroll?: boolean;
    only?: string[];
    enabled?: boolean;
    scrollDebounce?: number;
    replace?: boolean;
}

interface UseInfiniteScrollReturn<T = unknown> {
    isLoading: boolean;
    hasMore: boolean;
    error: string | null;
    loadMore: () => void;
    clearError: () => void;
    allData: T[];
}

interface PaginationPayload<T> {
    data: T[];
    current_page: number;
    next_page_url: string | null;
}

interface CancelToken {
    cancel: () => void;
}

interface RememberedInfiniteScrollState<T> {
    allData: T[];
    pages: Array<{ page: number; data: T[] }>;
    error: string | null;
    isAutoPaused: boolean;
    currentPage: number;
    scrollY: number;
}

function normalizePage(value: number): number {
    if (!Number.isFinite(value) || value < 1) {
        return 1;
    }

    return Math.floor(value);
}

export function useInfiniteScroll<T = unknown>({
    data,
    currentPage,
    nextPageUrl,
    rememberKey,
    restoreScroll = true,
    threshold = 200,
    preserveState = true,
    preserveScroll = true,
    only,
    enabled = true,
    scrollDebounce = 100,
    replace = true,
}: UseInfiniteScrollOptions<T>): UseInfiniteScrollReturn<T> {
    const restoredState = rememberKey
        ? (router.restore(rememberKey) as RememberedInfiniteScrollState<T> | null)
        : null;

    const normalizedInitialPage = normalizePage(restoredState?.currentPage ?? currentPage);
    const initialPages = restoredState?.pages?.length
        ? new Map(restoredState.pages.map((entry) => [normalizePage(entry.page), entry.data] as const))
        : new Map([[normalizedInitialPage, restoredState?.allData?.length ? restoredState.allData : data] as const]);

    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(restoredState?.error ?? null);
    const [isAutoPaused, setIsAutoPaused] = useState(restoredState?.isAutoPaused ?? false);
    const [allData, setAllData] = useState<T[]>(restoredState?.allData?.length ? restoredState.allData : data);
    const cancelTokenRef = useRef<CancelToken | null>(null);
    const inFlightRef = useRef(false);
    const retryPendingRef = useRef(false);
    const previousNearBottomRef = useRef(false);
    const pendingScrollRestoreRef = useRef<number | null>(restoreScroll && typeof restoredState?.scrollY === 'number' ? restoredState.scrollY : null);
    const pagesRef = useRef<Map<number, T[]>>(initialPages);

    const hasMore = !!nextPageUrl;

    const mergePages = useCallback((pages: Map<number, T[]>) => {
        const orderedPages = Array.from(pages.keys()).sort((a, b) => a - b);
        setAllData(orderedPages.flatMap((page) => pages.get(page) ?? []));
    }, []);

    useEffect(() => {
        const normalizedCurrentPage = normalizePage(currentPage);
        const currentPages = pagesRef.current;
        const loadedPageNumbers = Array.from(currentPages.keys());

        if (loadedPageNumbers.length === 0) {
            pagesRef.current = new Map([[normalizedCurrentPage, data]]);
            setAllData(data);
            return;
        }

        const minLoadedPage = Math.min(...loadedPageNumbers);
        const maxLoadedPage = Math.max(...loadedPageNumbers);
        const shouldResetPages = normalizedCurrentPage < minLoadedPage || normalizedCurrentPage > maxLoadedPage + 1;

        if (shouldResetPages) {
            cancelTokenRef.current?.cancel();
            cancelTokenRef.current = null;
            inFlightRef.current = false;
            retryPendingRef.current = false;
            pagesRef.current = new Map([[normalizedCurrentPage, data]]);
            setAllData(data);
            setError(null);
            setIsAutoPaused(false);
            return;
        }

        const nextPages = new Map(currentPages);
        nextPages.set(normalizedCurrentPage, data);
        pagesRef.current = nextPages;
        mergePages(nextPages);
    }, [currentPage, data, mergePages]);

    const rememberState = useCallback(
        (scrollY: number | null = null) => {
            if (!rememberKey || typeof window === 'undefined') {
                return;
            }

            const rememberedPages = Array.from(pagesRef.current.entries()).map(([page, pageData]) => ({
                page,
                data: pageData,
            }));
            const resolvedScrollY = scrollY ?? window.scrollY ?? window.pageYOffset ?? 0;
            const highestPage = rememberedPages.length
                ? Math.max(...rememberedPages.map((entry) => normalizePage(entry.page)))
                : normalizePage(currentPage);

            router.remember(
                {
                    allData,
                    pages: rememberedPages,
                    error,
                    isAutoPaused,
                    currentPage: highestPage,
                    scrollY: resolvedScrollY,
                } satisfies RememberedInfiniteScrollState<T>,
                rememberKey,
            );
        },
        [allData, currentPage, error, isAutoPaused, rememberKey],
    );

    useEffect(() => {
        rememberState();
    }, [rememberState]);

    useEffect(() => {
        if (!rememberKey || typeof window === 'undefined') {
            return;
        }

        let timeoutId: ReturnType<typeof setTimeout> | null = null;

        const handleScroll = () => {
            if (timeoutId) {
                clearTimeout(timeoutId);
            }

            timeoutId = setTimeout(() => {
                rememberState(window.scrollY ?? window.pageYOffset ?? 0);
            }, 100);
        };

        window.addEventListener('scroll', handleScroll, { passive: true });

        return () => {
            if (timeoutId) {
                clearTimeout(timeoutId);
            }
            window.removeEventListener('scroll', handleScroll);
        };
    }, [rememberKey, rememberState]);

    useEffect(() => {
        if (!restoreScroll || pendingScrollRestoreRef.current === null || typeof window === 'undefined') {
            return;
        }

        const scrollY = pendingScrollRestoreRef.current;
        pendingScrollRestoreRef.current = null;

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                window.scrollTo({ top: scrollY, left: 0, behavior: 'instant' });
            });
        });
    }, [allData.length, restoreScroll]);

    const extractPayload = useCallback(
        (props: Record<string, unknown>): PaginationPayload<T> | null => {
            const onlyKey = only?.[0];
            if (!onlyKey) {
                return null;
            }

            const rawPayload = props[onlyKey];
            if (!rawPayload || typeof rawPayload !== 'object') {
                return null;
            }

            const payload = rawPayload as Partial<PaginationPayload<T>>;
            if (!Array.isArray(payload.data) || typeof payload.current_page !== 'number') {
                return null;
            }

            return {
                data: payload.data,
                current_page: payload.current_page,
                next_page_url: typeof payload.next_page_url === 'string' || payload.next_page_url === null ? payload.next_page_url : null,
            };
        },
        [only],
    );

    const loadMoreInternal = useCallback(
        (mode: 'auto' | 'manual', hasRetried: boolean = false) => {
            if (!enabled || !nextPageUrl || inFlightRef.current) {
                return;
            }

            if (mode === 'auto' && (isAutoPaused || error !== null)) {
                return;
            }

            if (mode === 'manual') {
                setError(null);
                setIsAutoPaused(false);
            }

            inFlightRef.current = true;
            retryPendingRef.current = false;
            setIsLoading(true);

            router.visit(nextPageUrl, {
                method: 'get',
                only,
                preserveState,
                preserveScroll,
                replace,
                onCancelToken: (token) => {
                    cancelTokenRef.current = token as CancelToken;
                },
                onSuccess: (page) => {
                    const payload = extractPayload(page.props as Record<string, unknown>);
                    if (!payload) {
                        return;
                    }

                    const normalizedPayloadPage = normalizePage(payload.current_page);
                    const nextPages = new Map(pagesRef.current);
                    nextPages.set(normalizedPayloadPage, payload.data);
                    pagesRef.current = nextPages;
                    mergePages(nextPages);
                    setError(null);
                    setIsAutoPaused(false);
                },
                onError: () => {
                    if (mode === 'auto' && !hasRetried) {
                        retryPendingRef.current = true;
                        return;
                    }

                    setError('Failed to load more items. Please try again.');
                    setIsAutoPaused(true);
                },
                onFinish: () => {
                    inFlightRef.current = false;
                    cancelTokenRef.current = null;
                    setIsLoading(false);

                    if (retryPendingRef.current) {
                        retryPendingRef.current = false;
                        loadMoreInternal(mode, true);
                    }
                },
            });
        },
        [enabled, error, extractPayload, isAutoPaused, mergePages, nextPageUrl, only, preserveScroll, preserveState, replace],
    );

    useEffect(() => {
        if (!enabled || !hasMore || isAutoPaused || error !== null) {
            return;
        }

        previousNearBottomRef.current = isNearBottom(threshold);

        const cleanup = onScroll(() => {
            const nearBottom = isNearBottom(threshold);
            const hasTransitionedToNearBottom = !previousNearBottomRef.current && nearBottom;
            previousNearBottomRef.current = nearBottom;

            if (hasTransitionedToNearBottom) {
                loadMoreInternal('auto');
            }
        }, scrollDebounce);

        return cleanup;
    }, [enabled, error, hasMore, isAutoPaused, loadMoreInternal, scrollDebounce, threshold]);

    useEffect(() => {
        return () => {
            rememberState();
            cancelTokenRef.current?.cancel();
            cancelTokenRef.current = null;
            inFlightRef.current = false;
            retryPendingRef.current = false;
        };
    }, [rememberState]);

    const loadMore = useCallback(() => {
        loadMoreInternal('manual');
    }, [loadMoreInternal]);

    const clearError = useCallback(() => {
        setError(null);
        setIsAutoPaused(false);
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
