import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type WatchlistItem, type WatchlistPageProps } from '@/types/watchlist';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { Bookmark, Film, Tv } from 'lucide-react';
import { ErrorBoundary, FallbackProps } from 'react-error-boundary';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Watchlist',
        href: '/watchlist',
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

export default function Watchlist() {
    const { props } = usePage<WatchlistPageProps>();
    const { delete: destroy } = useForm();
    const { items, filter } = props;

    // Function to render a watchlist item card
    const renderWatchlistItem = (item: WatchlistItem) => {
        const detailUrl =
            item.type === 'movie'
                ? route('movies.show', { model: item.watchable_id })
                : route('series.show', { model: item.watchable_id });

        return (
            <motion.div
                key={item.id}
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -20 }}
                transition={{ duration: 0.3 }}
                className="max-w-[300px] min-w-[250px] flex-1"
            >
                <Card className="flex h-full flex-col overflow-hidden transition-shadow hover:shadow-md">
                    <div className="relative aspect-[2/3] overflow-hidden bg-gray-100 dark:bg-gray-800">
                        <img
                            src={item.cover}
                            alt={item.name}
                            className="h-full w-full object-cover transition-transform duration-300 hover:scale-105"
                        />
                        <div className="absolute top-2 right-2 rounded-md bg-black/60 p-1">
                            {item.type === 'movie' ? (
                                <Film className="h-4 w-4 text-white" />
                            ) : (
                                <Tv className="h-4 w-4 text-white" />
                            )}
                        </div>
                    </div>
                    <CardHeader className="p-4 pb-2">
                        <CardTitle className="truncate text-lg">{item.name}</CardTitle>
                        <CardDescription className="text-xs">Added {item.added_at}</CardDescription>
                    </CardHeader>
                    <CardContent className="flex-grow p-4 pt-0">{/* Additional content can go here */}</CardContent>
                    <CardFooter className="flex justify-between p-4 pt-0">
                        <Button asChild variant="default" size="sm">
                            <Link href={detailUrl}>View Details</Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            className="text-red-500 hover:bg-red-50 hover:text-red-700"
                            onClick={() => {
                                destroy(route('watchlist', { id: item.id }));
                            }}
                        >
                            <Bookmark className="h-4 w-4" />
                        </Button>
                    </CardFooter>
                </Card>
            </motion.div>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Watchlist" />

            <ErrorBoundary FallbackComponent={ErrorFallback}>
                <div className="container py-8">
                    <div className="flex flex-col gap-12 space-y-8 p-6">
                        <div className="flex flex-col space-y-2">
                            <h1 className="text-3xl font-bold tracking-tight">Your Watchlist</h1>
                            <p className="text-muted-foreground">
                                Keep track of movies and series you want to watch later.
                            </p>
                        </div>

                        <Tabs defaultValue={filter} className="w-full">
                            <TabsList className="mb-8">
                                <TabsTrigger value="all" asChild>
                                    <Link href={route('watchlist', { _query: { filter: 'all' } })} preserveState>
                                        All Items
                                    </Link>
                                </TabsTrigger>
                                <TabsTrigger value="movies" asChild>
                                    <Link href={route('watchlist', { _query: { filter: 'movies' } })} preserveState>
                                        Movies
                                    </Link>
                                </TabsTrigger>
                                <TabsTrigger value="series" asChild>
                                    <Link href={route('watchlist', { _query: { filter: 'series' } })} preserveState>
                                        Series
                                    </Link>
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value={filter} className="mt-0">
                                {items.length === 0 ? (
                                    <div className="flex flex-col items-center justify-center py-12 text-center">
                                        <Bookmark className="text-muted-foreground mb-4 h-12 w-12" />
                                        <h3 className="mb-2 text-xl font-medium">Your watchlist is empty</h3>
                                        <p className="text-muted-foreground mb-6 max-w-md">
                                            {filter === 'all'
                                                ? 'Start adding movies and series to your watchlist to keep track of what you want to watch.'
                                                : filter === 'movies'
                                                  ? "You haven't added any movies to your watchlist yet."
                                                  : "You haven't added any series to your watchlist yet."}
                                        </p>
                                        <Button asChild>
                                            <Link href={filter === 'series' ? route('series') : route('movies')}>
                                                Browse {filter === 'series' ? 'Series' : 'Movies'}
                                            </Link>
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                                        <AnimatePresence>{items.map(renderWatchlistItem)}</AnimatePresence>
                                    </div>
                                )}
                            </TabsContent>
                        </Tabs>
                    </div>
                </div>
            </ErrorBoundary>
        </AppLayout>
    );
}
