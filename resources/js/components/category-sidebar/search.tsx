import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { cn } from '@/lib/utils';
import { Fragment } from 'react';
import { CATEGORY_SIDEBAR_ALL_CATEGORIES_ID, CategorySidebarItem } from './types';

export interface CategorySidebarSearchSegment {
    text: string;
    matched: boolean;
}

export interface CategorySidebarSearchResult {
    item: CategorySidebarItem;
    score: number;
    segments: CategorySidebarSearchSegment[];
}

export interface CategorySidebarSearchResultsProps {
    query: string;
    results: CategorySidebarSearchResult[];
    showResults?: boolean;
    placeholder?: string;
    emptyTitle?: string;
    emptyDescription?: string;
    className?: string;
    onQueryChange: (nextQuery: string) => void;
    onSelectCategory: (nextCategory: string) => void;
    onClear: () => void;
}

export function normalizeCategorySearchQuery(query: string): string {
    return query.trim().replace(/\s+/g, ' ').toLocaleLowerCase();
}

export function buildCategorySearchSegments(label: string, query: string): CategorySidebarSearchSegment[] {
    const normalizedQuery = normalizeCategorySearchQuery(query);

    if (normalizedQuery === '') {
        return [{ text: label, matched: false }];
    }

    const lowerLabel = label.toLocaleLowerCase();
    const ranges = normalizedQuery
        .split(' ')
        .filter((token) => token !== '')
        .flatMap((token) => collectTokenRanges(lowerLabel, token));

    if (ranges.length === 0) {
        return [{ text: label, matched: false }];
    }

    const mergedRanges = mergeRanges(ranges);
    const segments: CategorySidebarSearchSegment[] = [];
    let cursor = 0;

    for (const [start, end] of mergedRanges) {
        if (start > cursor) {
            segments.push({ text: label.slice(cursor, start), matched: false });
        }

        segments.push({ text: label.slice(start, end), matched: true });
        cursor = end;
    }

    if (cursor < label.length) {
        segments.push({ text: label.slice(cursor), matched: false });
    }

    return segments.filter((segment) => segment.text !== '');
}

export function buildCategorySearchResults(args: {
    query: string;
    items: CategorySidebarItem[];
    uncategorizedItem: CategorySidebarItem | null;
}): CategorySidebarSearchResult[] {
    const normalizedQuery = normalizeCategorySearchQuery(args.query);

    if (normalizedQuery === '') {
        return [];
    }

    const items = dedupeSearchItems([...args.items, ...(args.uncategorizedItem ? [args.uncategorizedItem] : [])]);
    const results = items
        .filter((item) => item.id !== CATEGORY_SIDEBAR_ALL_CATEGORIES_ID)
        .flatMap((item) => {
            const score = scoreCategorySearchResult(normalizedQuery, item.name);

            if (score === null) {
                return [];
            }

            return [{ item, score, segments: buildCategorySearchSegments(item.name, normalizedQuery) } satisfies CategorySidebarSearchResult];
        });

    return results.sort((left, right) => {
        if (left.item.isUncategorized !== right.item.isUncategorized) {
            return left.item.isUncategorized ? 1 : -1;
        }

        if (left.score !== right.score) {
            return right.score - left.score;
        }

        if (left.item.isIgnored !== right.item.isIgnored) {
            return left.item.isIgnored ? 1 : -1;
        }

        return left.item.name.localeCompare(right.item.name);
    });
}

export function CategorySidebarSearchResults({
    query,
    results,
    showResults = true,
    placeholder = 'Search categories',
    emptyTitle = 'No categories match your search.',
    emptyDescription = 'Try a different category name or clear the current query.',
    className,
    onQueryChange,
    onSelectCategory,
    onClear,
}: CategorySidebarSearchResultsProps) {
    return (
        <Command loop shouldFilter={false} className={cn('rounded-lg border bg-background', className)}>
            <CommandInput value={query} onValueChange={onQueryChange} placeholder={placeholder} />
            {showResults && (
                <CommandList>
                    <CommandEmpty>
                        <div className="space-y-3 px-4 py-6 text-center">
                            <div className="space-y-1">
                                <p className="text-sm font-semibold">{emptyTitle}</p>
                                <p className="text-xs text-muted-foreground">{emptyDescription}</p>
                            </div>
                            <Button type="button" variant="outline" size="sm" onClick={onClear}>
                                Clear search
                            </Button>
                        </div>
                    </CommandEmpty>
                    {results.map((result) => (
                        <CommandItem key={result.item.id} value={result.item.id} keywords={[normalizeCategorySearchQuery(result.item.name)]} onSelect={() => onSelectCategory(result.item.id)}>
                            <div className="flex w-full items-start justify-between gap-3">
                                <div className="min-w-0 text-left text-sm leading-5">
                                    {result.segments.map((segment, index) => (
                                        <Fragment key={`${result.item.id}-${index}`}>
                                            {segment.matched ? <span className="font-semibold text-foreground">{segment.text}</span> : <span className="text-muted-foreground">{segment.text}</span>}
                                        </Fragment>
                                    ))}
                                </div>
                                {result.item.isIgnored && <span className="shrink-0 rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Ignored</span>}
                            </div>
                        </CommandItem>
                    ))}
                </CommandList>
            )}
        </Command>
    );
}

function dedupeSearchItems(items: CategorySidebarItem[]): CategorySidebarItem[] {
    const seen = new Set<string>();

    return items.filter((item) => {
        if (seen.has(item.id)) {
            return false;
        }

        seen.add(item.id);

        return true;
    });
}

function collectTokenRanges(label: string, token: string): Array<[number, number]> {
    const ranges: Array<[number, number]> = [];
    let searchFrom = 0;

    while (searchFrom < label.length) {
        const index = label.indexOf(token, searchFrom);

        if (index === -1) {
            break;
        }

        ranges.push([index, index + token.length]);
        searchFrom = index + token.length;
    }

    return ranges;
}

function mergeRanges(ranges: Array<[number, number]>): Array<[number, number]> {
    const sortedRanges = [...ranges].sort(([leftStart, leftEnd], [rightStart, rightEnd]) => {
        if (leftStart !== rightStart) {
            return leftStart - rightStart;
        }

        return leftEnd - rightEnd;
    });

    return sortedRanges.reduce<Array<[number, number]>>((merged, [start, end]) => {
        const previous = merged.at(-1);

        if (!previous || start > previous[1]) {
            merged.push([start, end]);

            return merged;
        }

        previous[1] = Math.max(previous[1], end);

        return merged;
    }, []);
}

function scoreCategorySearchResult(normalizedQuery: string, label: string): number | null {
    const normalizedLabel = normalizeCategorySearchQuery(label);

    if (normalizedLabel === '') {
        return null;
    }

    let score = 0;

    if (normalizedLabel === normalizedQuery) {
        score += 2_000;
    }

    if (normalizedLabel.startsWith(normalizedQuery)) {
        score += 1_200;
    }

    const phraseIndex = normalizedLabel.indexOf(normalizedQuery);

    if (phraseIndex >= 0) {
        score += 900 - phraseIndex * 10;
    }

    for (const token of normalizedQuery.split(' ').filter((value) => value !== '')) {
        const tokenIndex = normalizedLabel.indexOf(token);

        if (tokenIndex >= 0) {
            score += 250 - tokenIndex * 5;
        }

        if (normalizedLabel.split(' ').some((word) => word.startsWith(token))) {
            score += 120;
        }
    }

    const subsequenceScore = scoreSubsequenceMatch(normalizedQuery.replaceAll(' ', ''), normalizedLabel.replaceAll(' ', ''));

    if (subsequenceScore === null && score === 0) {
        return null;
    }

    return score + (subsequenceScore ?? 0);
}

function scoreSubsequenceMatch(query: string, label: string): number | null {
    if (query === '') {
        return null;
    }

    let searchIndex = 0;
    let spanStart = -1;
    let lastMatch = -1;
    let score = 0;

    for (const character of query) {
        const nextIndex = label.indexOf(character, searchIndex);

        if (nextIndex === -1) {
            return null;
        }

        if (spanStart === -1) {
            spanStart = nextIndex;
        }

        score += nextIndex === lastMatch + 1 ? 40 : 12;
        lastMatch = nextIndex;
        searchIndex = nextIndex + 1;
    }

    const spanLength = lastMatch - spanStart + 1;

    return score + Math.max(0, 120 - spanLength * 4);
}
