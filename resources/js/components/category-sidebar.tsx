import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import { ArrowLeft, ListFilterIcon } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { CategorySidebarBrowse } from './category-sidebar/browse';
import { CategorySidebarManage } from './category-sidebar/manage';
import { buildCategorySearchResults, CategorySidebarSearchResults } from './category-sidebar/search';
import {
    CategorySidebarData,
    CategorySidebarItem,
    CategorySidebarMutationOptions,
    CategorySidebarPreferencesSnapshot,
    CategorySidebarProps,
} from './category-sidebar/types';

export { type CategorySidebarMutationOptions, type CategorySidebarPreferencesSnapshot };
export { type CategorySidebarData, type CategorySidebarItem };

const ALL_CATEGORIES_ID = 'all-categories';

function buildEditableGroups(categories: CategorySidebarData | null | undefined) {
    const visibleItems = categories?.visibleItems ?? [];

    return {
        allCategoriesItem:
            visibleItems.find((item) => item.id === ALL_CATEGORIES_ID) ??
            ({
                id: ALL_CATEGORIES_ID,
                name: 'All categories',
                disabled: false,
                canNavigate: true,
                canEdit: false,
                isPinned: false,
                isHidden: false,
                isIgnored: false,
                pinRank: undefined,
                sortOrder: undefined,
                isUncategorized: false,
            } satisfies CategorySidebarItem),
        uncategorizedItem: visibleItems.find((item) => item.isUncategorized) ?? null,
        pinnedItems: visibleItems.filter((item) => item.canEdit && item.isPinned && !item.isIgnored && !item.isUncategorized),
        visibleItems: visibleItems.filter((item) => item.canEdit && !item.isPinned && !item.isIgnored && !item.isUncategorized),
        ignoredVisibleItems: visibleItems.filter((item) => item.canEdit && item.isIgnored && !item.isUncategorized),
        hiddenItems: (categories?.hiddenItems ?? []).filter((item) => item.canEdit && !item.isUncategorized),
    };
}

function buildSnapshot(
    pinnedItems: CategorySidebarItem[],
    visibleItems: CategorySidebarItem[],
    ignoredVisibleItems: CategorySidebarItem[],
    hiddenItems: CategorySidebarItem[],
): CategorySidebarPreferencesSnapshot {
    return {
        pinnedIds: pinnedItems.map((item) => item.id),
        visibleIds: [...pinnedItems, ...visibleItems].map((item) => item.id),
        hiddenIds: hiddenItems.map((item) => item.id),
        ignoredIds: ignoredVisibleItems.map((item) => item.id),
    };
}

function orderBySortOrder(items: CategorySidebarItem[]) {
    return [...items].sort((left, right) => {
        const leftOrder = left.sortOrder ?? Number.MAX_SAFE_INTEGER;
        const rightOrder = right.sortOrder ?? Number.MAX_SAFE_INTEGER;

        if (leftOrder !== rightOrder) {
            return leftOrder - rightOrder;
        }

        return left.name.localeCompare(right.name);
    });
}

function orderByPinRank(items: CategorySidebarItem[]) {
    return [...items].sort((left, right) => {
        const leftRank = left.pinRank ?? Number.MAX_SAFE_INTEGER;
        const rightRank = right.pinRank ?? Number.MAX_SAFE_INTEGER;

        if (leftRank !== rightRank) {
            return leftRank - rightRank;
        }

        return left.name.localeCompare(right.name);
    });
}

export default function CategorySidebar(props: CategorySidebarProps) {
    const {
        title,
        categories,
        desktopHeight,
        onSelectCategory,
        onSavePreferences,
        onResetPreferences,
        manageRequestKey,
        className,
    } = props;

    const [isMobileSheetOpen, setIsMobileSheetOpen] = useState(false);
    const [view, setView] = useState<'browse' | 'manage'>('browse');
    const [feedback, setFeedback] = useState<string | null>(null);
    const [isSaving, setIsSaving] = useState(false);
    const [query, setQuery] = useState('');

    const [pinnedItems, setPinnedItems] = useState<CategorySidebarItem[]>([]);
    const [visibleItems, setVisibleItems] = useState<CategorySidebarItem[]>([]);
    const [ignoredVisibleItems, setIgnoredVisibleItems] = useState<CategorySidebarItem[]>([]);
    const [hiddenItems, setHiddenItems] = useState<CategorySidebarItem[]>([]);
    const lastManageRequestKey = useRef<number | undefined>(manageRequestKey);

    const canManage = typeof onSavePreferences === 'function';
    const pinLimit = categories?.pinLimit ?? 5;

    const { allCategoriesItem, uncategorizedItem } = useMemo(() => buildEditableGroups(categories), [categories]);

    useEffect(() => {
        const nextGroups = buildEditableGroups(categories);
        setPinnedItems(nextGroups.pinnedItems);
        setVisibleItems(nextGroups.visibleItems);
        setIgnoredVisibleItems(nextGroups.ignoredVisibleItems);
        setHiddenItems(nextGroups.hiddenItems);
        setFeedback(null);
        setQuery('');
    }, [categories]);

    useEffect(() => {
        if (manageRequestKey === undefined || manageRequestKey === lastManageRequestKey.current) {
            return;
        }

        lastManageRequestKey.current = manageRequestKey;

        if (manageRequestKey > 0) {
            setView('manage');
            setIsMobileSheetOpen(true);
        }
    }, [manageRequestKey]);

    useEffect(() => {
        if (view !== 'browse' && !isMobileSheetOpen) {
            setQuery('');
        }
    }, [isMobileSheetOpen, view]);

    const runSave = (
        nextPinnedItems: CategorySidebarItem[],
        nextVisibleItems: CategorySidebarItem[],
        nextIgnoredVisibleItems: CategorySidebarItem[],
        nextHiddenItems: CategorySidebarItem[],
    ) => {
        const previousPinnedItems = pinnedItems;
        const previousVisibleItems = visibleItems;
        const previousIgnoredVisibleItems = ignoredVisibleItems;
        const previousHiddenItems = hiddenItems;

        setPinnedItems(nextPinnedItems);
        setVisibleItems(nextVisibleItems);
        setIgnoredVisibleItems(nextIgnoredVisibleItems);
        setHiddenItems(nextHiddenItems);
        setFeedback(null);

        if (!onSavePreferences) return;

        setIsSaving(true);
        onSavePreferences(buildSnapshot(nextPinnedItems, nextVisibleItems, nextIgnoredVisibleItems, nextHiddenItems), {
            onError: (message) => {
                setPinnedItems(previousPinnedItems);
                setVisibleItems(previousVisibleItems);
                setIgnoredVisibleItems(previousIgnoredVisibleItems);
                setHiddenItems(previousHiddenItems);
                setFeedback(message);
            },
            onFinish: () => setIsSaving(false),
        });
    };

    const handleTogglePin = (item: CategorySidebarItem) => {
        if (isSaving) return;
        if (item.isIgnored) return;

        if (item.isPinned) {
            const nextPinnedItems = pinnedItems.filter((entry) => entry.id !== item.id);
            const nextVisibleItems = orderBySortOrder([...visibleItems, { ...item, isPinned: false, pinRank: undefined }]);
            runSave(nextPinnedItems, nextVisibleItems, ignoredVisibleItems, hiddenItems);
            return;
        }

        if (pinnedItems.length >= pinLimit) {
            setFeedback(`Pin limit reached (${pinLimit}). Unpin another category first.`);
            return;
        }

        const nextVisibleItems = visibleItems.filter((entry) => entry.id !== item.id);
        const nextPinnedItems = [...pinnedItems, { ...item, isPinned: true }];
        runSave(nextPinnedItems, nextVisibleItems, ignoredVisibleItems, hiddenItems);
    };

    const handleHide = (item: CategorySidebarItem) => {
        if (isSaving) return;

        const nextPinnedItems = pinnedItems.filter((entry) => entry.id !== item.id);
        const nextVisibleItems = visibleItems.filter((entry) => entry.id !== item.id);
        const nextIgnoredVisibleItems = ignoredVisibleItems.filter((entry) => entry.id !== item.id);
        const nextHiddenItems = orderBySortOrder([
            ...hiddenItems,
            { ...item, isPinned: false, isHidden: true, isIgnored: false, pinRank: undefined },
        ]);

        runSave(nextPinnedItems, nextVisibleItems, nextIgnoredVisibleItems, nextHiddenItems);
    };

    const handleUnhide = (item: CategorySidebarItem) => {
        if (isSaving) return;

        const nextHiddenItems = hiddenItems.filter((entry) => entry.id !== item.id);
        const nextVisibleItems = orderBySortOrder([...visibleItems, { ...item, isHidden: false, isPinned: false, pinRank: undefined }]);

        runSave(pinnedItems, nextVisibleItems, ignoredVisibleItems, nextHiddenItems);
    };

    const handleIgnore = (item: CategorySidebarItem) => {
        if (isSaving || item.isIgnored) return;

        const nextPinnedItems = pinnedItems.filter((entry) => entry.id !== item.id);
        const nextVisibleItems = visibleItems.filter((entry) => entry.id !== item.id);
        const nextIgnoredVisibleItems = orderBySortOrder([
            ...ignoredVisibleItems,
            { ...item, isHidden: false, isIgnored: true },
        ]);

        runSave(nextPinnedItems, nextVisibleItems, nextIgnoredVisibleItems, hiddenItems);
    };

    const handleUnignore = (item: CategorySidebarItem) => {
        if (isSaving || !item.isIgnored) return;

        const nextIgnoredVisibleItems = ignoredVisibleItems.filter((entry) => entry.id !== item.id);

        if (item.isPinned) {
            const nextPinnedItems = orderByPinRank([...pinnedItems, { ...item, isIgnored: false }]);
            runSave(nextPinnedItems, visibleItems, nextIgnoredVisibleItems, hiddenItems);

            return;
        }

        const nextVisibleItems = orderBySortOrder([...visibleItems, { ...item, isIgnored: false }]);
        runSave(pinnedItems, nextVisibleItems, nextIgnoredVisibleItems, hiddenItems);
    };

    const handleReorder = (group: 'pinned' | 'visible', nextItems: CategorySidebarItem[]) => {
        if (group === 'pinned') {
            runSave(nextItems, visibleItems, ignoredVisibleItems, hiddenItems);
        } else {
            runSave(pinnedItems, nextItems, ignoredVisibleItems, hiddenItems);
        }
    };

    const handleReset = () => {
        if (!onResetPreferences || isSaving) return;
        setFeedback(null);
        setIsSaving(true);
        onResetPreferences({
            onError: (message) => setFeedback(message),
            onFinish: () => setIsSaving(false),
        });
    };

    const handleSelectAndClose = (id: string | null) => {
        setView('browse');
        setIsMobileSheetOpen(false);

        if (typeof window === 'undefined') {
            onSelectCategory(id);

            return;
        }

        window.setTimeout(() => {
            onSelectCategory(id);
        }, 0);
    };

    const trimmedQuery = query.trim();
    const isSearchActive = trimmedQuery !== '';
    const searchResults = useMemo(() => {
        if (!isSearchActive) {
            return [];
        }

        return buildCategorySearchResults({
            query: trimmedQuery,
            items: [...pinnedItems, ...visibleItems, ...ignoredVisibleItems],
            uncategorizedItem,
        });
    }, [ignoredVisibleItems, isSearchActive, pinnedItems, trimmedQuery, uncategorizedItem, visibleItems]);

    const commonManageProps = {
        pinnedItems,
        visibleItems,
        ignoredVisibleItems,
        hiddenItems,
        allCategoriesItem,
        uncategorizedItem,
        isSaving,
        feedback,
        pinLimit,
        onTogglePin: handleTogglePin,
        onIgnore: handleIgnore,
        onUnignore: handleUnignore,
        onHide: handleHide,
        onUnhide: handleUnhide,
        onReorder: handleReorder,
        onReset: handleReset,
    };

    const browseProps = {
        ...props,
        pinnedItems,
        visibleItems,
        ignoredVisibleItems,
        allCategoriesItem,
        uncategorizedItem,
        isSaving,
        canManage,
        onTogglePin: handleTogglePin,
        onIgnore: handleIgnore,
        onUnignore: handleUnignore,
        onHide: handleHide,
        onManage: () => setView('manage'),
        onSelectCategory: handleSelectAndClose,
    };

    const manageProps = {
        ...props,
        ...commonManageProps,
        onDone: () => setView('browse'),
    };

    return (
        <>
            <aside className={cn('hidden self-stretch md:flex', className)}>
                <div
                    className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden rounded-xl border bg-card shadow-sm"
                    style={desktopHeight ? { height: `${desktopHeight}px` } : undefined}
                >
                    <div className="flex items-center justify-between p-4 pb-2">
                        <h2 className="text-[10px] font-bold tracking-widest text-muted-foreground uppercase">{title}</h2>
                        {view === 'manage' && (
                            <button type="button" onClick={() => setView('browse')} className="inline-flex h-8 items-center rounded-md px-2 text-[10px] font-bold hover:bg-accent transition-colors">
                                <ArrowLeft className="mr-1 h-3 w-3" />
                                Back
                            </button>
                        )}
                    </div>

                    {view === 'browse' && (
                        <div className="px-4 pb-2">
                            <CategorySidebarSearchResults
                                query={query}
                                results={searchResults}
                                showResults={view === 'browse' && isSearchActive}
                                className="border-border/70 shadow-none"
                                onQueryChange={setQuery}
                                onSelectCategory={handleSelectAndClose}
                                onClear={() => setQuery('')}
                            />
                        </div>
                    )}

                    <ScrollArea className="min-h-0 h-full flex-1">
                        <div className="space-y-4 p-4 pt-2">
                            {view === 'manage' ? (
                                <CategorySidebarManage {...manageProps} />
                            ) : isSearchActive ? null : (
                                <CategorySidebarBrowse {...browseProps} />
                            )}
                        </div>
                    </ScrollArea>
                </div>
            </aside>

            <div className="md:hidden">
                <Sheet open={isMobileSheetOpen} onOpenChange={(open) => {
                    setIsMobileSheetOpen(open);
                    if (!open) {
                        setView('browse');
                        setQuery('');
                    }
                }}>
                    <SheetTrigger asChild>
                        <Button type="button" variant="outline" className="w-full justify-start gap-2 h-11 font-semibold shadow-sm border-muted-foreground/20">
                            <ListFilterIcon className="h-4 w-4" />
                            {title}
                        </Button>
                    </SheetTrigger>
                    <SheetContent
                        side="bottom"
                        role="dialog"
                        onOpenAutoFocus={(event) => event.preventDefault()}
                        className="flex flex-col h-[90vh] w-full max-w-none rounded-t-2xl p-0 outline-none"
                    >
                        <SheetHeader className="px-6 py-4 border-b">
                            <SheetTitle className="text-left font-bold">{view === 'manage' ? `${title} Manager` : title}</SheetTitle>
                        </SheetHeader>

                        <div className="flex-1 overflow-y-auto px-6 py-6">
                            <div className="space-y-6">
                                <CategorySidebarSearchResults
                                    query={query}
                                    results={searchResults}
                                    showResults={isSearchActive}
                                    className="border-border/70 shadow-none"
                                    onQueryChange={setQuery}
                                    onSelectCategory={handleSelectAndClose}
                                    onClear={() => setQuery('')}
                                />

                                {isSearchActive ? null : view === 'manage' ? (
                                    <CategorySidebarManage {...manageProps} isMobile />
                                ) : (
                                    <CategorySidebarBrowse {...browseProps} isMobile />
                                )}
                            </div>
                        </div>

                        {view === 'manage' && (
                            <div className="p-6 border-t bg-muted/20">
                                <Button type="button" className="w-full h-12 font-bold shadow-lg" onClick={() => setView('browse')}>
                                    Done Managing
                                </Button>
                            </div>
                        )}
                    </SheetContent>
                </Sheet>
            </div>
        </>
    );
}
