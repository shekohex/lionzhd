import EmptyState from '@/components/empty-state';
import MediaCard from '@/components/media-card';
import MediaSection from '@/components/media-section';
import { Pagination } from '@/components/ui/pagination';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { SeriesPageProps } from '@/types/series';
import { Head, Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { TvIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
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

export default function Series() {
    const { props } = usePage<SeriesPageProps>();
    const {
        series: { data: series, total },
    } = props;

    const [isLoading, setIsLoading] = useState(true);
    const hasSeries = total > 0;

    useEffect(() => {
        // Simulate loading state for demonstration
        const timer = setTimeout(() => {
            setIsLoading(false);
        }, 200);

        return () => clearTimeout(timer);
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Series" />

            <ErrorBoundary FallbackComponent={ErrorFallback}>
                <div className="flex flex-col gap-12 p-6">
                    {/* Series Section */}
                    <MediaSection title="Latest TV Shows">
                        {isLoading ? (
                            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                                {Array.from({ length: 5 }).map((_, i) => (
                                    <div key={i} className="aspect-[2/3] animate-pulse rounded-lg bg-gray-200" />
                                ))}
                            </div>
                        ) : !hasSeries ? (
                            <EmptyState
                                icon={<TvIcon className="h-12 w-12" />}
                                title="No TV shows available"
                                description="There are no TV series available at the moment. Check back later or explore other content."
                                className="bg-muted/30"
                            />
                        ) : (
                            <>
                                <motion.div
                                    className="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"
                                    variants={container}
                                    initial="hidden"
                                    animate="show"
                                >
                                    {series.map((show) => (
                                        <motion.div key={show.series_id} variants={item}>
                                            <Link
                                                preserveState
                                                preserveScroll
                                                href={route('series.show', { model: show.series_id })}
                                            >
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
                                <div className="flex justify-center">
                                    {/* Series pagination - with section-specific partial reload */}
                                    {props.series?.links && props.series.links.length > 3 && (
                                        <div className="mt-8">
                                            <Pagination
                                                links={props.series.links}
                                                preserveState={true}
                                                preserveScroll={true}
                                                prefetch={true}
                                                only={['series', 'filters']}
                                            />
                                        </div>
                                    )}
                                </div>
                            </>
                        )}
                    </MediaSection>
                </div>
            </ErrorBoundary>
        </AppLayout>
    );
}
