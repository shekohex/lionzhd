import EmptyState from '@/components/empty-state';
import MediaCard from '@/components/media-card';
import CategorySidebar from '@/components/category-sidebar';
import MediaSection from '@/components/media-section';
import { Button } from '@/components/ui/button';
import { DualPagination } from '@/components/ui/enhanced-pagination';
import { useInfiniteScroll } from '@/hooks/use-infinite-scroll';
import { useIsMobile } from '@/hooks/use-mobile';
import AppLayout from '@/layouts/app-layout';
import { scrollToTop } from '@/lib/scroll-utils';
import { type BreadcrumbItem } from '@/types';
import { SeriesPageProps } from '@/types/series';
import type { PendingVisit } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { TvIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ErrorBoundary, FallbackProps } from 'react-error-boundary';

const container = {
    hidden: { opacity: 0 },
    show: {
        opacity: 1,
        transition: {
            staggerChildren: 0.05,
        },
    },
};

const item = {
    hidden: { opacity: 0, y: 20 },
    show: { opacity: 1, y: 0 },
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Series',
        href: '/series',
    },
];

const CATEGORY_LOAD_ERROR_MESSAGE = 'Unable to load categories right now. Please try again.';

function ErrorFallback({ error, resetErrorBoundary }: FallbackProps) {
    return (
        <div className="flex flex-col items-center justify-center rounded-lg border border-red-300 bg-red-50 p-4 text-center text-red-800">
            <p className="text-lg font-medium">Something went wrong:</p>
            <pre className="mt-2 overflow-auto text-sm">{error.message}</pre>
            <button
                onClick={resetErrorBoundary}
                className="mt-4 rounded bg-red-600 px-4 py-2 text-white transition-colors hover:bg-red-700"
            >
                Try again
            </button>
        </div>
    );
}

function MediaGridSkeleton({ count }: { count: number }) {
    return (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            {Array.from({ length: count }).map((_, index) => (
                <div key={index} className="aspect-[2/3] animate-pulse rounded-lg bg-gray-200" />
            ))}
        </div>
    );
}

interface SeriesResultsProps {
    series: SeriesPageProps['series'];
    isMobile: boolean;
    isSwitchingCategory: boolean;
    selectedCategory: string | null;
    onResetToAllCategories: () => void;
}

function SeriesResults({
    series,
    isMobile,
    isSwitchingCategory,
    selectedCategory,
    onResetToAllCategories,
}: SeriesResultsProps) {
    const hasSeries = series.total > 0;
    const rememberKey = `Series/InfiniteScroll:${selectedCategory ?? 'all'}`;

    const infiniteScroll = useInfiniteScroll({
        data: series.data,
        currentPage: series.current_page,
        nextPageUrl: series.next_page_url,
        rememberKey,
        enabled: isMobile,
        only: ['series'],
        preserveState: true,
        preserveScroll: true,
    });

    const displayedSeries = isMobile ? infiniteScroll.allData : series.data;

    if (isSwitchingCategory) {
        return <MediaGridSkeleton count={10} />;
    }

    if (!hasSeries) {
        if (selectedCategory !== null) {
            return (
                <EmptyState
                    icon={<TvIcon className="h-12 w-12" />}
                    title="No TV shows found in this category"
                    description="Try another category or reset back to all categories."
                    className="bg-muted/30"
                    action={
                        <Button type="button" variant="outline" onClick={onResetToAllCategories}>
                            Show all categories
                        </Button>
                    }
                />
            );
        }

        return (
            <EmptyState
                icon={<TvIcon className="h-12 w-12" />}
                title="No TV shows available"
                description="There are no TV series available at the moment. Check back later or explore other content."
                className="bg-muted/30"
            />
        );
    }

    return (
        <>
            {!isMobile && series.links && series.links.length > 3 && (
                <div className="mb-8">
                    <DualPagination
                        links={series.links}
                        preserveState={true}
                        preserveScroll={true}
                        prefetch={true}
                        only={['series']}
                        showTop={true}
                        showBottom={false}
                        topClassName="border-b border-border pb-4"
                    />
                </div>
            )}

            <motion.div
                className="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"
                variants={container}
                initial="hidden"
                animate="show"
            >
                {displayedSeries.map((show) => (
                    <motion.div key={show.series_id} variants={item}>
                        <Link preserveState preserveScroll href={route('series.show', { model: show.series_id })}>
                            <MediaCard
                                title={show.name}
                                posterUrl={show.cover}
                                rating={show.rating_5based}
                                inWatchlist={show.in_watchlist}
                            />
                        </Link>
                    </motion.div>
                ))}
            </motion.div>

            {series.links && series.links.length > 3 && (
                <div className="mt-8">
                    <DualPagination
                        links={series.links}
                        preserveState={true}
                        preserveScroll={true}
                        prefetch={true}
                        only={['series']}
                        showTop={false}
                        showBottom={true}
                        infiniteScroll={isMobile ? infiniteScroll : undefined}
                        bottomClassName="border-t border-border pt-4"
                    />
                </div>
            )}
        </>
    );
}

export default function Series() {
    const { props } = usePage<SeriesPageProps>();
    const { series, categories, filters } = props;
    const isMobile = useIsMobile();
    const [isSwitchingCategory, setIsSwitchingCategory] = useState(false);
    const [categoryLoadError, setCategoryLoadError] = useState<string | null>(null);
    const categoryVisitCancelToken = useRef<{ cancel: () => void } | null>(null);

    const selectedCategory = filters.category ?? null;
    const resultsKey = selectedCategory ?? 'all';

    const handleCategoryVisitFinish = (visit: PendingVisit) => {
        setIsSwitchingCategory(false);
        categoryVisitCancelToken.current = null;

        if (!visit.completed && !visit.cancelled && !visit.interrupted) {
            setCategoryLoadError(CATEGORY_LOAD_ERROR_MESSAGE);
        }
    };

    useEffect(() => {
        return () => {
            categoryVisitCancelToken.current?.cancel();
            categoryVisitCancelToken.current = null;
        };
    }, []);

    const handleSelectCategory = (nextCategory: string | null) => {
        const category = nextCategory === selectedCategory ? null : nextCategory;
        categoryVisitCancelToken.current?.cancel();

        scrollToTop('smooth');

        router.visit(category ? route('series', { category }) : route('series'), {
            method: 'get',
            only: ['series', 'filters', 'categories'],
            preserveState: true,
            preserveScroll: false,
            onCancelToken: (token) => {
                categoryVisitCancelToken.current = token as { cancel: () => void };
            },
            onStart: () => {
                setIsSwitchingCategory(true);
                setCategoryLoadError(null);
            },
            onSuccess: () => {
                setCategoryLoadError(null);
            },
            onError: () => {
                setCategoryLoadError(CATEGORY_LOAD_ERROR_MESSAGE);
                setIsSwitchingCategory(false);
            },
            onFinish: handleCategoryVisitFinish,
        });
    };

    const handleRetryCategories = () => {
        scrollToTop('instant');

        router.reload({
            only: ['series', 'filters', 'categories'],
            onCancelToken: (token) => {
                categoryVisitCancelToken.current = token as { cancel: () => void };
            },
            onStart: () => {
                setIsSwitchingCategory(true);
                setCategoryLoadError(null);
            },
            onSuccess: () => {
                setCategoryLoadError(null);
            },
            onError: () => {
                setCategoryLoadError(CATEGORY_LOAD_ERROR_MESSAGE);
                setIsSwitchingCategory(false);
            },
            onFinish: handleCategoryVisitFinish,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Series" />

            <ErrorBoundary FallbackComponent={ErrorFallback}>
                <div className="flex flex-col gap-6 p-6 md:flex-row md:items-start">
                    <CategorySidebar
                        title="Series Categories"
                        categories={categories}
                        selectedCategory={selectedCategory}
                        onSelectCategory={handleSelectCategory}
                        error={categoryLoadError}
                        onRetryCategories={handleRetryCategories}
                    />

                    <div className="min-w-0 flex-1">
                        <MediaSection title="Latest TV Shows">
                            <SeriesResults
                                key={resultsKey}
                                series={series}
                                isMobile={isMobile}
                                isSwitchingCategory={isSwitchingCategory}
                                selectedCategory={selectedCategory}
                                onResetToAllCategories={() => handleSelectCategory(null)}
                            />
                        </MediaSection>
                    </div>
                </div>
            </ErrorBoundary>
        </AppLayout>
    );
}
