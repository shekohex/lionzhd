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
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { MediaType, SearchRequest, SearchResult, SortBy } from '@/types/search';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { ChevronDownIcon, FilterIcon, LoaderIcon, SearchIcon, XIcon } from 'lucide-react';
import { useCallback, useEffect, useMemo } from 'react';
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

const filterNameMap = (filterName: string) => (filterName === 'type' ? 'media_type' : filterName);

// Parse a search query for magic words
function parseSearchQuery(query: string) {
    const filters: Record<string, string> = {};
    let baseQuery = query;

    // Extract magic filter words
    const filterRegex = /(type|sort):([^\s]+)/g;
    let match;

    while ((match = filterRegex.exec(query)) !== null) {
        const [fullMatch, filterName, filterValue] = match;
        if (filterName && filterValue) {
            filters[filterNameMap(filterName)] = filterValue;
            baseQuery = baseQuery.replace(fullMatch, '');
        }
    }

    // Clean up the base query
    baseQuery = baseQuery.trim();

    return { baseQuery, filters };
}

export default function Search() {
    const { props } = usePage<SearchResult<'full'>>();
    const { data, setData, get, processing } = useForm<SearchRequest>(props.filters);

    // Parse query for magic words
    const parsedQuery = useMemo(() => {
        return data.q ? parseSearchQuery(data.q) : { baseQuery: '', filters: {} };
    }, [data.q]);

    const performSearch = useCallback(
        (options: { preserveState?: boolean; preserveScroll?: boolean } = {}) => {
            const defaultOptions = { preserveState: true, preserveScroll: true };
            const searchOptions = { ...defaultOptions, ...options };

            get(route('search.full'), searchOptions);
        },
        [get],
    );

    // Apply filters from parsed query
    useEffect(() => {
        if (parsedQuery.filters.media_type && parsedQuery.filters.media_type !== data.media_type) {
            setData('media_type', parsedQuery.filters.media_type as MediaType);
        }

        if (parsedQuery.filters.sort_by && parsedQuery.filters.sort_by !== data.sort_by) {
            setData('sort_by', parsedQuery.filters.sort_by as SortBy);
        }
    }, [parsedQuery, setData, data]);

    // Handle search form submission
    const handleSearch = (request: SearchRequest) => {
        setData('q', request.q);
        performSearch({ preserveScroll: false });
    };

    // Handle filter selection
    const handleFilterSelect = (filterType: string, value: string) => {
        if (filterType === 'type') {
            setData('media_type', value as MediaType);
        } else if (filterType === 'sort') {
            setData('sort_by', value as SortBy);
        }

        // Update query to include magic word
        const currentQueryParts = data.q?.split(' ') || [];
        const filterPattern = new RegExp(`${filterType}:[^\\s]+`);
        const newQueryParts = currentQueryParts.filter((part) => !filterPattern.test(part));
        newQueryParts.push(`${filterType}:${value}`);

        setData('q', newQueryParts.join(' '));

        // Auto-submit search after filter change
        setTimeout(() => performSearch({ preserveScroll: false }), 0);
    };

    // Calculate combined search results
    const allResults = useMemo(() => [...(props.movies?.data ?? []), ...(props.series?.data ?? [])], [props]);
    const hasResults = allResults.length > 0;
    const total = useMemo(() => (props.movies?.total || 0) + (props.series?.total || 0), [props]);

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
                                onSubmit={handleSearch}
                                fullWidth
                                autoFocus
                            />
                        </div>
                    </div>

                    {/* Active filters */}
                    <div className="mt-4 flex flex-wrap gap-2">
                        {data.media_type && (
                            <Badge variant="outline" className="flex items-center gap-1 py-1 pr-1 pl-2">
                                <span>Type: {data.media_type}</span>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="h-4 w-4"
                                    onClick={() => {
                                        // Remove type:value from query
                                        const newQuery = data?.q?.replace(/type:[^\s]+/, '').trim();
                                        setData('q', newQuery);
                                        setData('media_type', undefined);
                                        setTimeout(() => performSearch({ preserveScroll: false }), 0);
                                    }}
                                >
                                    <XIcon className="h-3 w-3" />
                                </Button>
                            </Badge>
                        )}
                        {data.sort_by && (
                            <Badge variant="outline" className="flex items-center gap-1 py-1 pr-1 pl-2">
                                <span>Sort: {data.sort_by}</span>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="h-4 w-4"
                                    onClick={() => {
                                        // Remove sort:value from query
                                        const newQuery = data?.q?.replace(/sort:[^\s]+/, '').trim();
                                        setData('q', newQuery);
                                        setData('sort_by', undefined);
                                        setTimeout(() => performSearch({ preserveScroll: false }), 0);
                                    }}
                                >
                                    <XIcon className="h-3 w-3" />
                                </Button>
                            </Badge>
                        )}
                    </div>
                </div>

                {/* Quick filters */}
                <div className="mx-auto mt-2 flex w-full max-w-3xl gap-2">
                    <Popover>
                        <PopoverTrigger asChild>
                            <Button variant="outline" size="sm" className="flex items-center">
                                <FilterIcon className="mr-2 h-4 w-4" />
                                Media Type
                                <ChevronDownIcon className="ml-2 h-3 w-3" />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent className="w-48">
                            <div className="space-y-2">
                                <Button
                                    variant={data.media_type ? 'default' : 'ghost'}
                                    className="w-full justify-start"
                                    onClick={() => {
                                        // Remove type:value from query
                                        const newQuery = data.q?.replace(/type:[^\s]+/, '').trim();
                                        setData('q', newQuery);
                                        setData('media_type', undefined);
                                        setTimeout(() => performSearch({ preserveScroll: false }), 0);
                                    }}
                                >
                                    All
                                </Button>
                                {FILTER_OPTIONS.type.map((option) => (
                                    <Button
                                        key={option.value}
                                        variant={data.media_type === option.value ? 'default' : 'ghost'}
                                        className="w-full justify-start"
                                        onClick={() => handleFilterSelect('type', option.value)}
                                    >
                                        {option.label}
                                    </Button>
                                ))}
                            </div>
                        </PopoverContent>
                    </Popover>

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
                                    variant={data.sort_by ? 'default' : 'ghost'}
                                    className="w-full justify-start"
                                    onClick={() => {
                                        // Remove sort:value from query
                                        const newQuery = data.q?.replace(/sort:[^\s]+/, '').trim();
                                        setData('q', newQuery);
                                        setData('sort_by', undefined);
                                        setTimeout(() => performSearch({ preserveScroll: false }), 0);
                                    }}
                                >
                                    Default
                                </Button>
                                {FILTER_OPTIONS.sort.map((option) => (
                                    <Button
                                        key={option.value}
                                        variant={data.sort_by === option.value ? 'default' : 'ghost'}
                                        className="w-full justify-start"
                                        onClick={() => handleFilterSelect('sort', option.value)}
                                    >
                                        {option.label}
                                    </Button>
                                ))}
                            </div>
                        </PopoverContent>
                    </Popover>
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
                            <div className="mx-auto w-full max-w-7xl">
                                {!hasResults && !processing && (
                                    <EmptyState
                                        icon={<SearchIcon className="h-12 w-12" />}
                                        title={data.q ? 'No results found' : 'Enter a search term'}
                                        description={
                                            data.q
                                                ? 'Try using different keywords or removing filters'
                                                : 'Type something to start searching'
                                        }
                                        className="bg-muted/30 py-16"
                                    />
                                )}

                                {hasResults && (
                                    <>
                                        {/* Total count */}
                                        <div className="mb-6">
                                            <p className="text-muted-foreground text-sm">
                                                Found {total} results for "
                                                <span className="text-foreground font-medium">
                                                    {props.filters?.q || data.q}
                                                </span>
                                                "
                                            </p>
                                        </div>

                                        {/* Movies section */}
                                        {props.movies?.total > 0 && (
                                            <MediaSection title="Movies">
                                                <motion.div
                                                    className="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"
                                                    variants={container}
                                                    initial="hidden"
                                                    animate="show"
                                                >
                                                    {props.movies?.data?.map((movie) => (
                                                        <motion.div key={movie.num} variants={item}>
                                                            <Link href={route('movies.show', { model: movie.num })}>
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

                                        {/* Series section */}
                                        {props.series?.total > 0 && (
                                            <MediaSection title="TV Series">
                                                <motion.div
                                                    className="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"
                                                    variants={container}
                                                    initial="hidden"
                                                    animate="show"
                                                >
                                                    {props.series?.data?.map((series) => (
                                                        <motion.div key={series.num} variants={item}>
                                                            <Link href={route('series.show', { model: series.num })}>
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

                                {processing && (
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
