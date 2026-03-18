import EmptyState from '@/components/empty-state';
import MediaCard from '@/components/media-card';
import CategorySidebar from '@/components/category-sidebar';
import MediaSection from '@/components/media-section';
import { Button } from '@/components/ui/button';
import { DualPagination } from '@/components/ui/enhanced-pagination';
import { useElementHeight } from '@/hooks/use-element-height';
import { useInfiniteScroll } from '@/hooks/use-infinite-scroll';
import { useIsMobile } from '@/hooks/use-mobile';
import { useCategoryBrowser } from '@/hooks/use-category-browser';
import { useResizableSidebar } from '@/hooks/use-resizable-sidebar';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { SeriesPageProps } from '@/types/series';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { GripVertical, TvIcon } from 'lucide-react';
import { ErrorBoundary, FallbackProps } from 'react-error-boundary';
import { useRef } from 'react';

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
    selectedCategoryIsIgnored: boolean;
    selectedCategoryName?: string;
    recovery: SeriesPageProps['filters']['recovery'];
    onResetToAllCategories: () => void;
    onManageCategories: () => void;
    onResetPreferences: () => void;
    onUnignoreSelectedCategory: () => void;
}

function describeSeriesRecoveryState(recovery: SeriesPageProps['filters']['recovery']) {
    if (recovery?.allCategoriesEmptyDueToIgnored && recovery?.allCategoriesEmptyDueToHidden) {
        return 'Ignored and hidden categories are filtering every series out. Manage categories to restore your view, or reset preferences as a fallback.';
    }

    if (recovery?.allCategoriesEmptyDueToIgnored) {
        return 'Ignored categories are filtering every series out. Manage categories to restore your view, or reset preferences as a fallback.';
    }

    return 'Hidden categories are removing every series from all categories. Manage categories to restore your view, or reset preferences as a fallback.';
}

function SeriesResults({
    series,
    isMobile,
    isSwitchingCategory,
    selectedCategory,
    selectedCategoryIsIgnored,
    selectedCategoryName,
    recovery,
    onResetToAllCategories,
    onManageCategories,
    onResetPreferences,
    onUnignoreSelectedCategory,
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
        if (selectedCategory !== null && selectedCategoryIsIgnored) {
            return (
                <EmptyState
                    icon={<TvIcon className="h-12 w-12" />}
                    title="This category is ignored"
                    description={
                        selectedCategoryName
                            ? `"${selectedCategoryName}" is currently ignored, so its series stay hidden until you unignore it.`
                            : 'This category is currently ignored, so its series stay hidden until you unignore it.'
                    }
                    className="bg-muted/30"
                    action={
                        <Button type="button" onClick={onUnignoreSelectedCategory}>
                            Unignore and restore results
                        </Button>
                    }
                />
            );
        }

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

        if (recovery?.allCategoriesEmptyDueToIgnored || recovery?.allCategoriesEmptyDueToHidden) {
            return (
                <EmptyState
                    icon={<TvIcon className="h-12 w-12" />}
                    title="Your series view is empty"
                    description={describeSeriesRecoveryState(recovery)}
                    className="bg-muted/30"
                    action={
                        <div className="flex flex-col items-center gap-3 sm:flex-row">
                            <Button type="button" onClick={onManageCategories}>
                                Manage categories
                            </Button>
                            <Button type="button" variant="outline" onClick={onResetPreferences}>
                                Reset preferences
                            </Button>
                        </div>
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
    const resultsRef = useRef<HTMLDivElement | null>(null);
    const { sidebarWidth, isResizing, startResizing, resetWidth } = useResizableSidebar(
        'discovery-categories-sidebar-width',
    );
    const resultsHeight = useElementHeight(resultsRef);

    const selectedCategory = filters.category ?? null;
    const resultsKey = selectedCategory ?? 'all';

    const {
        isSwitchingCategory,
        categoryLoadError,
        manageRequestKey,
        handleSelectCategory,
        handleRetryCategories,
        handleSavePreferences,
        handleResetPreferences,
        handleUnignoreCategory,
        requestManageMode,
    } = useCategoryBrowser({
        routeName: 'series',
        mediaType: 'series',
        only: ['series', 'filters', 'categories'],
    });

    const reloadSeries = () => {
        router.reload({
            only: ['series', 'filters', 'categories'],
        });
    };

    const categoryHiddenBanner = categories.selectedCategoryIsHidden ? (
        <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50/50 px-4 py-3.5 text-sm text-amber-900 shadow-sm backdrop-blur-sm animate-in fade-in slide-in-from-top-2">
            <p className="font-semibold mb-0.5">Hidden Category Active</p>
            <p className="opacity-80">
                {categories.selectedCategoryName
                    ? `"${categories.selectedCategoryName}" is currently hidden. Results stay visible until you switch categories or unhide it.`
                    : 'This category is hidden. Results stay visible until you switch categories or unhide it.'}
            </p>
        </div>
    ) : null;

    const results = (
        <MediaSection title="Latest TV Shows">
            <SeriesResults
                key={resultsKey}
                series={series}
                isMobile={isMobile}
                isSwitchingCategory={isSwitchingCategory}
                selectedCategory={selectedCategory}
                selectedCategoryIsIgnored={categories.selectedCategoryIsIgnored}
                selectedCategoryName={categories.selectedCategoryName}
                recovery={filters.recovery}
                onResetToAllCategories={() => handleSelectCategory(null, selectedCategory)}
                onManageCategories={requestManageMode}
                onResetPreferences={() => handleResetPreferences({ onSuccess: reloadSeries })}
                onUnignoreSelectedCategory={() =>
                    handleUnignoreCategory(selectedCategory, categories, {
                        onSuccess: reloadSeries,
                    })
                }
            />
        </MediaSection>
    );

    const sidebar = (
        <CategorySidebar
            title="Series Categories"
            categories={categories}
            selectedCategory={selectedCategory}
            desktopHeight={resultsHeight}
            onSelectCategory={(next) => handleSelectCategory(next, selectedCategory)}
            error={categoryLoadError}
            onRetryCategories={handleRetryCategories}
            onSavePreferences={handleSavePreferences}
            onResetPreferences={handleResetPreferences}
            manageRequestKey={manageRequestKey}
            className="w-full"
        />
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Series" />

            <ErrorBoundary FallbackComponent={ErrorFallback}>
                {isMobile ? (
                    <div className="flex flex-col gap-6 p-6">
                        {sidebar}
                        <div className="min-w-0 flex-1">
                            {categoryHiddenBanner}
                            {results}
                        </div>
                    </div>
                ) : (
                    <div className="space-y-4 p-6">
                        {categoryHiddenBanner}
                        <div className="hidden items-stretch gap-2 md:flex">
                            <div className="min-h-0 shrink-0 self-stretch" style={{ width: `${sidebarWidth}px` }}>
                                {sidebar}
                            </div>
                            <div
                                role="separator"
                                aria-label="Resize category sidebar"
                                aria-orientation="vertical"
                                onPointerDown={startResizing}
                                onDoubleClick={resetWidth}
                                className="group relative flex w-6 shrink-0 cursor-col-resize touch-none select-none self-stretch items-center justify-center"
                            >
                                <div
                                    className={
                                        isResizing
                                            ? 'absolute inset-y-0 left-1/2 w-1 -translate-x-1/2 rounded-full bg-primary/80'
                                            : 'absolute inset-y-0 left-1/2 w-px -translate-x-1/2 rounded-full bg-border/80 transition-colors group-hover:w-1 group-hover:bg-primary/70'
                                    }
                                />
                                <div className="z-10 flex h-12 w-5 items-center justify-center rounded-full border bg-background shadow-md ring-1 ring-border/70 transition-all group-hover:scale-110 group-hover:bg-accent group-active:scale-95">
                                    <GripVertical className="h-4 w-4 text-muted-foreground" />
                                </div>
                            </div>
                            <div className="min-w-0 flex-1 self-stretch pl-2">
                                <div ref={resultsRef}>{results}</div>
                            </div>
                        </div>
                    </div>
                )}
            </ErrorBoundary>
        </AppLayout>
    );
}
