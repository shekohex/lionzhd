import { Command, CommandEmpty, CommandGroup, CommandItem, CommandList } from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import { useDebounce } from '@/hooks/use-debounce';
import { LightweightSearchResult } from '@/types/search';
import { router, useForm, usePage } from '@inertiajs/react';
import { FilmIcon, SearchIcon, TvIcon, XIcon } from 'lucide-react';
import { FormEvent, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { type ValidRouteName } from 'ziggy-js';

interface SearchInputProps {
    searchRoute: ValidRouteName;
    placeholder?: string;
    className?: string;
    onSubmit?: (query: App.Data.SearchMediaData) => void;
    autoFocus?: boolean;
    onClear?: () => void;
    showSearchIcon?: boolean;
    defaultPerPage?: number;
    fullWidth?: boolean;
}

export function SearchInput({
    placeholder = 'Search...',
    className = '',
    searchRoute,
    onSubmit,
    autoFocus = false,
    onClear,
    showSearchIcon = true,
    fullWidth = false,
    defaultPerPage = 5,
}: SearchInputProps) {
    const { props: autocompleteData } = usePage<LightweightSearchResult>();

    const isFullSearch = useMemo(() => searchRoute === 'search.full', [searchRoute]);
    const [isFocused, setIsFocused] = useState(false);
    const [isAutocompleteOpen, setIsAutocompleteOpen] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const formRef = useRef<HTMLFormElement>(null);
    const searchContainerRef = useRef<HTMLDivElement>(null);

    const { data, setData, processing, post, get } = useForm<App.Data.SearchMediaData>({
        q: autocompleteData.filters?.q ?? '',
        page: 1,
        per_page: autocompleteData.filters?.per_page ?? defaultPerPage,
        sort_by: 'latest',
    });
    // Debounce search query
    const debouncedQuery = useDebounce(data.q || '', 500);

    // Handle autocomplete call
    useEffect(() => {
        if (!debouncedQuery || debouncedQuery.length < 2) {
            setIsAutocompleteOpen(false);
            return;
        }

        async function fetchSuggestions() {
            const callMethod = isFullSearch ? get : post;
            callMethod(route(searchRoute), {
                preserveScroll: true,
                preserveState: true,
                preserveUrl: !isFullSearch,
                only: !isFullSearch ? ['movies', 'series', 'filters'] : [],
                onFinish: () => {
                    setIsAutocompleteOpen(!isFullSearch);
                },
            });
        }
        if (isFullSearch) {
            setTimeout(fetchSuggestions, 0);
        } else {
            fetchSuggestions();
        }
    }, [debouncedQuery, post, get, isFullSearch, searchRoute]);

    // Handle click outside to close autocomplete
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (searchContainerRef.current && !searchContainerRef.current.contains(event.target as Node)) {
                setIsAutocompleteOpen(false);
                setIsFocused(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    // Handle form submission
    const handleSubmit = useCallback(
        (e?: FormEvent) => {
            e?.preventDefault();

            if (data.q && data.q.trim().length > 2) {
                if (onSubmit) {
                    onSubmit(data);
                } else {
                    // Default behavior: navigate to search page with query using Inertia
                    router.visit(route('search.full'), {
                        method: 'get',
                        data: { q: data.q },
                        preserveState: true,
                        replace: true,
                    });
                }
            }

            setIsAutocompleteOpen(false);
        },
        [data, onSubmit],
    );

    // Handle keyboard shortcuts
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            // Cmd+K or Ctrl+K to focus search
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                inputRef.current?.focus();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    const totalResults = useMemo(
        () => (autocompleteData?.movies?.total ?? 0) + (autocompleteData?.series?.total ?? 0),
        [autocompleteData],
    );

    // Handle suggestion selection
    const handleSuggestionSelect = useCallback(
        (suggestionText: string) => {
            setData('q', suggestionText);
            // Use a small timeout to ensure the query state is updated
            setTimeout(() => {
                handleSubmit();
            }, 0);
        },
        [handleSubmit, setData],
    );

    return (
        <div ref={searchContainerRef} className={`relative ${fullWidth ? 'w-full' : 'w-auto'}`}>
            <form ref={formRef} onSubmit={handleSubmit} className="relative">
                <div
                    className={`relative flex items-center ${isFocused ? 'ring-primary/20 border-primary ring-2' : ''}`}
                >
                    {showSearchIcon && (
                        <SearchIcon className="text-muted-foreground absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2" />
                    )}

                    <Input
                        ref={inputRef}
                        type="search"
                        placeholder={placeholder}
                        className={`${showSearchIcon ? 'pl-10' : 'pl-4'} py-6 pr-12 text-lg ${className}`}
                        value={data.q || ''}
                        onChange={(e) => setData('q', e.target.value)}
                        onFocus={() => {
                            setIsFocused(true);
                            // Show autocomplete if query is long enough
                            if (data.q && data.q.length >= 2) {
                                setIsAutocompleteOpen(true);
                            }
                        }}
                        autoFocus={autoFocus}
                    />

                    {data.q && (
                        <button
                            type="button"
                            className="text-muted-foreground hover:text-foreground absolute top-1/2 right-10 -translate-y-1/2"
                            onClick={() => {
                                setData('q', '');
                                onClear?.();
                                inputRef.current?.focus();
                                setIsAutocompleteOpen(false);
                            }}
                        >
                            <XIcon className="h-4 w-4" />
                            <span className="sr-only">Clear search</span>
                        </button>
                    )}

                    <div className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2">
                        <kbd className="border-border text-muted-foreground/70 inline-flex h-5 items-center rounded border px-1 font-mono text-[0.625rem] font-medium">
                            ⌘K
                        </kbd>
                    </div>
                </div>

                {/* Autocomplete dropdown */}
                {isAutocompleteOpen && (
                    <div className="bg-popover absolute top-full right-0 left-0 z-30 mt-1 max-h-[300px] overflow-hidden rounded-lg border shadow-lg">
                        <Command>
                            <CommandList>
                                {processing ? (
                                    <div className="flex items-center justify-center py-6">
                                        <div className="border-primary h-5 w-5 animate-spin rounded-full border-b-2"></div>
                                    </div>
                                ) : (
                                    <>
                                        <CommandEmpty>No results found</CommandEmpty>
                                        <CommandGroup heading="Suggestions">
                                            {autocompleteData?.movies?.data?.map((result) => (
                                                <CommandItem
                                                    key={result.num}
                                                    onSelect={() => handleSuggestionSelect(result.name)}
                                                    className="cursor-pointer"
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <FilmIcon className="h-4 w-4" />
                                                        <span>{result.name}</span>
                                                    </div>
                                                </CommandItem>
                                            ))}
                                            {autocompleteData?.series?.data?.map((result) => (
                                                <CommandItem
                                                    key={result.num}
                                                    onSelect={() => handleSuggestionSelect(result.name)}
                                                    className="cursor-pointer"
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <TvIcon className="h-4 w-4" />
                                                        <span>{result.name}</span>
                                                    </div>
                                                </CommandItem>
                                            ))}
                                        </CommandGroup>
                                    </>
                                )}

                                {!totalResults && data.q && data.q.length >= 2 && !processing && (
                                    <div className="text-muted-foreground px-2 py-3 text-center text-sm">
                                        Press Enter to search for "{data.q}"
                                    </div>
                                )}
                            </CommandList>
                        </Command>
                    </div>
                )}
            </form>
        </div>
    );
}
