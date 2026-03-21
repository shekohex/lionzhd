import EmptyState from '@/components/empty-state';
import MediaCard from '@/components/media-card';
import MediaSection from '@/components/media-section';
import { SearchInput } from '@/components/search-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { FullSearchResult } from '@/types/search';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { ChevronDownIcon, FilterIcon, LoaderIcon, SearchIcon, XIcon } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { ErrorBoundary } from 'react-error-boundary';

// Animation variants
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
        title: 'Search',
        href: '/search',
    },
];

// Filter option definitions
const FILTER_OPTIONS = {
    type: [
        { value: 'movie', label: 'Movies' },
        { value: 'series', label: 'TV Series' },
    ],
    sort: [
        { value: 'popular', label: 'Popularity' },
        { value: 'latest', label: 'Latest' },
        { value: 'rating', label: 'Rating' },
    ],
};

interface SearchVisitOverrides {
    q?: string | null;
    page?: number;
    per_page?: number;
    media_type?: App.Enums.MediaType;
    sort_by?: App.Enums.SearchSortby;
}

const MIXED_GRID_CLASS = 'grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5';
const FILTERED_GRID_CLASS = 'grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4';

function formatResultCount(count: number, singular: string, plural = `${singular}s`) {
    return `${count} ${count === 1 ? singular : plural}`;
}

export default function Search() {
    const { props } = usePage<FullSearchResult>();
    const [draftQuery, setDraftQuery] = useState(props.filters.q ?? '');
    const [isCommitting, setIsCommitting] = useState(false);
    const activeMode = props.filters.media_type === 'movie' || props.filters.media_type === 'series' ? props.filters.media_type : 'all';

    useEffect(() => {
        setDraftQuery(props.filters.q ?? '');
    }, [props.filters.q]);

    const performSearch = useCallback(
        (
            overrides: SearchVisitOverrides = {},
            options: { preserveScroll?: boolean } = {},
        ) => {
            const hasQueryOverride = Object.prototype.hasOwnProperty.call(overrides, 'q');
            const hasMediaTypeOverride = Object.prototype.hasOwnProperty.call(overrides, 'media_type');
            const hasSortByOverride = Object.prototype.hasOwnProperty.call(overrides, 'sort_by');
            const rawQuery = hasQueryOverride ? (overrides.q ?? '') : draftQuery;
            const searchUrl = route('search.full', {
                q: hasQueryOverride ? rawQuery : (rawQuery.trim() ? rawQuery : undefined),
                page: overrides.page ?? props.filters.page ?? 1,
                per_page: props.filters.per_page,
                media_type: hasMediaTypeOverride ? overrides.media_type : props.filters.media_type,
                sort_by: hasSortByOverride ? overrides.sort_by : props.filters.sort_by,
            });

            router.get(
                searchUrl,
                {},
                {
                    preserveState: true,
                    preserveScroll: options.preserveScroll ?? false,
                    onStart: () => setIsCommitting(true),
                    onFinish: () => setIsCommitting(false),
                },
            );
        },
        [draftQuery, props.filters.media_type, props.filters.page, props.filters.per_page, props.filters.sort_by],
    );

    const handleSearch = (request: App.Data.SearchMediaData) => {
        const nextQuery = request.q ?? '';

        setDraftQuery(nextQuery);
        performSearch({ q: nextQuery, page: 1 }, { preserveScroll: false });
    };

    const handleFilterSelect = (filterType: string, value: string) => {
        if (filterType === 'type') {
            performSearch({ media_type: value as App.Enums.MediaType, page: 1 }, { preserveScroll: false });

            return;
        }

        performSearch({ sort_by: value as App.Enums.SearchSortby, page: 1 }, { preserveScroll: false });
    };

    const handleModeChange = (value: string) => {
        const nextMode = value === 'all' ? undefined : (value as App.Enums.MediaType);

        if (nextMode === props.filters.media_type || (value === 'all' && !props.filters.media_type)) {
            return;
        }

        performSearch({ q: draftQuery, media_type: nextMode, page: 1 }, { preserveScroll: false });
    };

    // Calculate combined search results
    const allResults = useMemo(() => [...(props.movies?.data ?? []), ...(props.series?.data ?? [])], [props]);
    const hasResults = allResults.length > 0;
    const total = useMemo(() => (props.movies?.total || 0) + (props.series?.total || 0), [props]);
    const isFilteredMode = activeMode !== 'all';
    const filteredModeLabel = activeMode === 'movie' ? 'Movies only' : 'TV Series only';
    const filteredResultCount = activeMode === 'movie' ? (props.movies?.total ?? 0) : (props.series?.total ?? 0);
    const filteredResultSummary =
        activeMode === 'movie'
            ? formatResultCount(filteredResultCount, 'movie result')
            : formatResultCount(filteredResultCount, 'TV series result');
    const movieSectionCount = formatResultCount(props.movies?.total ?? 0, 'movie result');
    const seriesSectionCount = formatResultCount(props.series?.total ?? 0, 'TV series result');
    const filteredEmptyTitle = activeMode === 'movie' ? 'No movies found' : 'No TV series found';
    const filteredEntryTitle = activeMode === 'movie' ? 'Search movies only' : 'Search TV series only';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Search Media" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {/* Search form - Centered on page */}
                <div className="mx-auto w-full max-w-3xl">
                    <div className="flex flex-col gap-3">
                        <Label htmlFor="search">Search the entire media library</Label>

                        <div className="relative z-20">
                            {/* Using the updated search input component with Inertia form handling */}
                            <SearchInput
                                placeholder="Search movies, TV series..."
                                searchRoute="search.full"
                                value={draftQuery}
                                onValueChange={setDraftQuery}
                                onSubmit={handleSearch}
                                onClear={() => setDraftQuery('')}
                                defaultPerPage={10}
                                fullWidth
                                autoFocus
                            />
                        </div>
                    </div>

                    <Tabs value={activeMode} onValueChange={handleModeChange} className="mt-4 w-full">
                        <TabsList className="grid h-auto w-full grid-cols-3 gap-1 rounded-xl p-1">
                            <TabsTrigger value="all" className="py-2" onClick={() => handleModeChange('all')}>
                                All
                            </TabsTrigger>
                            <TabsTrigger value="movie" className="py-2" onClick={() => handleModeChange('movie')}>
                                Movies
                            </TabsTrigger>
                            <TabsTrigger value="series" className="py-2" onClick={() => handleModeChange('series')}>
                                TV Series
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>

                    <div className="mt-4 flex flex-wrap gap-2">
                        {props.filters.sort_by && (
                            <Badge variant="outline" className="flex items-center gap-1 py-1 pr-1 pl-2">
                                <span>Sort: {props.filters.sort_by}</span>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="h-4 w-4"
                                    onClick={() =>
                                        performSearch({ sort_by: 'latest' as App.Enums.SearchSortby, page: 1 }, { preserveScroll: false })
                                    }
                                >
                                    <XIcon className="h-3 w-3" />
                                </Button>
                            </Badge>
                        )}
                    </div>
                </div>

                <div className="mx-auto mt-2 flex w-full max-w-3xl gap-2">
                    <Popover>
                        <PopoverTrigger asChild>
                            <Button variant="outline" size="sm" className="flex items-center">
                                <FilterIcon className="mr-2 h-4 w-4" />
                                Sort By
                                <ChevronDownIcon className="ml-2 h-3 w-3" />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent className="w-48">
                            <div className="space-y-2">
                                <Button
                                    variant={props.filters.sort_by === 'latest' ? 'default' : 'ghost'}
                                    className="w-full justify-start"
                                    onClick={() =>
                                        performSearch({ sort_by: 'latest' as App.Enums.SearchSortby, page: 1 }, { preserveScroll: false })
                                    }
                                >
                                    Default
                                </Button>
                                {FILTER_OPTIONS.sort.map((option) => (
                                    <Button
                                        key={option.value}
                                        variant={props.filters.sort_by === option.value ? 'default' : 'ghost'}
                                        className="w-full justify-start"
                                        onClick={() => handleFilterSelect('sort', option.value)}
                                    >
                                        {option.label}
                                    </Button>
                                ))}
                            </div>
                        </PopoverContent>
                    </Popover>

                    {(draftQuery || props.filters.q) && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                setDraftQuery('');
                                performSearch({ q: null, page: 1 }, { preserveScroll: false });
                            }}
                        >
                            Reset search
                        </Button>
                    )}
                </div>

                {/* Search results */}
                <ErrorBoundary
                    fallbackRender={({ error, resetErrorBoundary }) => (
                        <div className="mt-8 text-center">
                            <div className="bg-destructive/10 inline-block rounded-lg p-6">
                                <h3 className="text-destructive text-lg font-medium">Search Error</h3>
                                <p className="mt-2 text-sm">{error.message}</p>
                                <Button variant="outline" onClick={() => resetErrorBoundary()} className="mt-4">
                                    Try again
                                </Button>
                            </div>
                        </div>
                    )}
                >
                    <ScrollArea className="h-[calc(100vh-240px)]">
                        <div className="flex flex-col gap-12 pt-4 pb-20">
                            <div className="mx-auto w-full max-w-7xl" data-search-layout={isFilteredMode ? 'filtered' : 'all'}>
                                {!hasResults && !isCommitting && (
                                    <EmptyState
                                        icon={<SearchIcon className="h-12 w-12" />}
                                        title={
                                            isFilteredMode
                                                ? (props.filters.q ? filteredEmptyTitle : filteredEntryTitle)
                                                : (props.filters.q ? 'No results found' : 'Enter a search term')
                                        }
                                        description={
                                            isFilteredMode
                                                ? (
                                                      props.filters.q
                                                          ? 'Try editing or clearing your search query.'
                                                          : 'Enter a search query to search this media type.'
                                                  )
                                                : (props.filters.q
                                                      ? 'Try using different keywords or removing filters'
                                                      : 'Type something to start searching')
                                        }
                                        className="bg-muted/30 py-16"
                                    />
                                )}

                                {hasResults && (
                                    <>
                                        <div className="mb-6">
                                            {isFilteredMode ? (
                                                <div className="space-y-2">
                                                    <h2 className="text-2xl font-bold">{filteredModeLabel}</h2>
                                                    <p className="text-muted-foreground text-sm">
                                                        {filteredResultSummary} for "
                                                        <span className="text-foreground font-medium">
                                                            {props.filters.q}
                                                        </span>
                                                        "
                                                    </p>
                                                </div>
                                            ) : (
                                                <div className="space-y-2">
                                                    <p className="text-muted-foreground text-sm">
                                                        Found {total} results for "
                                                        <span className="text-foreground font-medium">
                                                            {props.filters.q}
                                                        </span>
                                                        "
                                                    </p>
                                                    <div className="flex flex-wrap gap-2 text-sm">
                                                        <Badge variant="secondary">Movies: {movieSectionCount}</Badge>
                                                        <Badge variant="secondary">TV Series: {seriesSectionCount}</Badge>
                                                    </div>
                                                </div>
                                            )}
                                        </div>

                                        {(activeMode === 'all' || activeMode === 'movie') && props.movies?.total > 0 && (
                                            <MediaSection title={isFilteredMode ? filteredModeLabel : 'Movies'}>
                                                <motion.div
                                                    className={isFilteredMode ? FILTERED_GRID_CLASS : MIXED_GRID_CLASS}
                                                    variants={container}
                                                    initial="hidden"
                                                    animate="show"
                                                >
                                                    {props.movies?.data?.map((movie) => (
                                                        <motion.div key={movie.stream_id} variants={item}>
                                                            <Link
                                                                href={route('movies.show', { model: movie.stream_id })}
                                                            >
                                                                <MediaCard
                                                                    title={movie.name}
                                                                    posterUrl={movie.stream_icon}
                                                                    rating={movie.rating_5based}
                                                                />
                                                            </Link>
                                                        </motion.div>
                                                    ))}
                                                </motion.div>

                                                {/* Movies pagination - with section-specific partial reload */}
                                                {props.movies?.links && props.movies.links.length > 3 && (
                                                    <div className="mt-8 flex justify-center">
                                                        <Pagination
                                                            links={props.movies.links}
                                                            preserveState={true}
                                                            prefetch={true}
                                                            only={['movies', 'filters']}
                                                        />
                                                    </div>
                                                )}
                                            </MediaSection>
                                        )}

                                        {(activeMode === 'all' || activeMode === 'series') && props.series?.total > 0 && (
                                            <MediaSection title={isFilteredMode ? filteredModeLabel : 'TV Series'}>
                                                <motion.div
                                                    className={isFilteredMode ? FILTERED_GRID_CLASS : MIXED_GRID_CLASS}
                                                    variants={container}
                                                    initial="hidden"
                                                    animate="show"
                                                >
                                                    {props.series?.data?.map((series) => (
                                                        <motion.div key={series.series_id} variants={item}>
                                                            <Link
                                                                href={route('series.show', { model: series.series_id })}
                                                            >
                                                                <MediaCard
                                                                    title={series.name}
                                                                    posterUrl={series.cover}
                                                                    rating={series.rating_5based}
                                                                />
                                                            </Link>
                                                        </motion.div>
                                                    ))}
                                                </motion.div>

                                                {/* Series pagination - with section-specific partial reload */}
                                                {props.series?.links && props.series.links.length > 3 && (
                                                    <div className="mt-8 flex justify-center">
                                                        <Pagination
                                                            links={props.series.links}
                                                            preserveState={true}
                                                            prefetch={true}
                                                            only={['series', 'filters']}
                                                        />
                                                    </div>
                                                )}
                                            </MediaSection>
                                        )}
                                    </>
                                )}

                                {isCommitting && (
                                    <div className="flex justify-center py-12">
                                        <LoaderIcon className="text-primary h-10 w-10 animate-spin" />
                                    </div>
                                )}
                            </div>
                        </div>
                    </ScrollArea>
                </ErrorBoundary>
            </div>
        </AppLayout>
    );
}
