import EmptyState from '@/components/empty-state';
import MediaCard from '@/components/media-card';
import CategorySidebar from '@/components/category-sidebar';
import MediaSection from '@/components/media-section';
import { DualPagination } from '@/components/ui/enhanced-pagination';
import { Button } from '@/components/ui/button';
import { useInfiniteScroll } from '@/hooks/use-infinite-scroll';
import { useIsMobile } from '@/hooks/use-mobile';
import AppLayout from '@/layouts/app-layout';
import { scrollToTop } from '@/lib/scroll-utils';
import { type BreadcrumbItem } from '@/types';
import { MoviesPageProps } from '@/types/movies';
import type { PendingVisit } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { FilmIcon } from 'lucide-react';
import { useState } from 'react';
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
        title: 'Movies',
        href: '/movies',
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

interface MoviesResultsProps {
    movies: MoviesPageProps['movies'];
    isMobile: boolean;
    isSwitchingCategory: boolean;
    selectedCategory: string | null;
    onResetToAllCategories: () => void;
}

function MoviesResults({
    movies,
    isMobile,
    isSwitchingCategory,
    selectedCategory,
    onResetToAllCategories,
}: MoviesResultsProps) {
    const hasMovies = movies.total > 0;

    const infiniteScroll = useInfiniteScroll({
        data: movies.data,
        links: movies.links || [],
        enabled: isMobile,
        only: ['movies'],
        preserveState: true,
        preserveScroll: false,
    });

    const displayedMovies = isMobile ? infiniteScroll.allData : movies.data;

    if (isSwitchingCategory) {
        return <MediaGridSkeleton count={10} />;
    }

    if (!hasMovies) {
        if (selectedCategory !== null) {
            return (
                <EmptyState
                    icon={<FilmIcon className="h-12 w-12" />}
                    title="No movies found in this category"
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
                icon={<FilmIcon className="h-12 w-12" />}
                title="No movies available"
                description="There are no movies available at the moment. Check back later or explore other content."
                className="bg-muted/30"
            />
        );
    }

    return (
        <>
            {!isMobile && movies.links && movies.links.length > 3 && (
                <div className="mb-8">
                    <DualPagination
                        links={movies.links}
                        preserveState={true}
                        preserveScroll={true}
                        prefetch={true}
                        only={['movies']}
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
                {displayedMovies.map((movie) => (
                    <motion.div key={movie.stream_id} variants={item}>
                        <Link href={route('movies.show', { model: movie.stream_id })}>
                            <MediaCard
                                title={movie.name}
                                posterUrl={movie.stream_icon}
                                rating={movie.rating_5based}
                                inWatchlist={movie.in_watchlist}
                            />
                        </Link>
                    </motion.div>
                ))}
            </motion.div>

            {movies.links && movies.links.length > 3 && (
                <div className="mt-8">
                    <DualPagination
                        links={movies.links}
                        preserveState={true}
                        preserveScroll={true}
                        prefetch={true}
                        only={['movies']}
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

export default function Movies() {
    const { props } = usePage<MoviesPageProps>();
    const { movies, categories, filters } = props;
    const isMobile = useIsMobile();
    const [isSwitchingCategory, setIsSwitchingCategory] = useState(false);
    const [categoryLoadError, setCategoryLoadError] = useState<string | null>(null);

    const selectedCategory = filters.category ?? null;
    const resultsKey = selectedCategory ?? 'all';

    const handleCategoryVisitFinish = (visit: PendingVisit) => {
        setIsSwitchingCategory(false);

        if (!visit.completed && !visit.cancelled && !visit.interrupted) {
            setCategoryLoadError(CATEGORY_LOAD_ERROR_MESSAGE);
        }
    };

    const handleSelectCategory = (nextCategory: string | null) => {
        const category = nextCategory === selectedCategory ? null : nextCategory;

        scrollToTop('instant');

        router.visit(category ? route('movies', { category }) : route('movies'), {
            method: 'get',
            only: ['movies', 'filters', 'categories'],
            preserveState: true,
            preserveScroll: false,
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
            only: ['movies', 'filters', 'categories'],
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
            <Head title="Movies" />

            <ErrorBoundary FallbackComponent={ErrorFallback}>
                <div className="flex flex-col gap-6 p-6 md:flex-row md:items-start">
                    <CategorySidebar
                        title="Movie Categories"
                        categories={categories}
                        selectedCategory={selectedCategory}
                        onSelectCategory={handleSelectCategory}
                        error={categoryLoadError}
                        onRetryCategories={handleRetryCategories}
                    />

                    <div className="min-w-0 flex-1">
                        <MediaSection title="Latest Movies">
                            <MoviesResults
                                key={resultsKey}
                                movies={movies}
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
