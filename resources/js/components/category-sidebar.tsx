import EmptyState from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import {
    closestCenter,
    DndContext,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
    type DragEndEvent,
    type DragStartEvent,
} from '@dnd-kit/core';
import {
    SortableContext,
    arrayMove,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import {
    ArrowLeft,
    ChevronDown,
    ChevronUp,
    Eye,
    EyeOff,
    GripVertical,
    ListFilterIcon,
    Pin,
    PinOff,
    RotateCcw,
    Settings2,
} from 'lucide-react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';

const ALL_CATEGORIES_ID = 'all-categories';

type CategorySidebarData = App.Data.CategorySidebarData;
type CategorySidebarItem = App.Data.CategorySidebarItemData;

export interface CategorySidebarPreferencesSnapshot {
    pinnedIds: string[];
    visibleIds: string[];
    hiddenIds: string[];
}

export interface CategorySidebarMutationOptions {
    onSuccess?: () => void;
    onError?: (message: string) => void;
    onFinish?: () => void;
}

interface CategorySidebarProps {
    title: string;
    categories: CategorySidebarData | null | undefined;
    selectedCategory: string | null;
    onSelectCategory: (nextCategory: string | null) => void;
    error: string | null;
    onRetryCategories: () => void;
    onSavePreferences?: (
        snapshot: CategorySidebarPreferencesSnapshot,
        options?: CategorySidebarMutationOptions,
    ) => void;
    onResetPreferences?: (options?: CategorySidebarMutationOptions) => void;
}

interface ManageCategoryRowProps {
    item: CategorySidebarItem;
    dragHandle?: ReactNode;
    actions?: ReactNode;
    muted?: boolean;
    fixedLabel?: string;
}

function ManageCategoryRow({ item, dragHandle, actions, muted = false, fixedLabel }: ManageCategoryRowProps) {
    return (
        <div
            className={cn(
                'flex items-center gap-3 rounded-md border bg-background px-3 py-2',
                muted && 'opacity-75',
            )}
        >
            {dragHandle}
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium">{item.name}</p>
                <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    {fixedLabel ? <span>{fixedLabel}</span> : null}
                    {!item.canNavigate ? <span>No items currently</span> : null}
                </div>
            </div>
            {actions}
        </div>
    );
}

function SortableManageCategoryRow({
    item,
    disabled,
    actions,
}: {
    item: CategorySidebarItem;
    disabled: boolean;
    actions: ReactNode;
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: item.id,
        disabled,
    });

    return (
        <div
            ref={setNodeRef}
            style={{
                transform: CSS.Transform.toString(transform),
                transition,
            }}
            className={cn(isDragging && 'opacity-40')}
        >
            <ManageCategoryRow
                item={item}
                muted={disabled}
                dragHandle={
                    <button
                        type="button"
                        className={cn(
                            'rounded-md border p-2 text-muted-foreground transition-colors',
                            disabled ? 'cursor-not-allowed opacity-50' : 'hover:bg-muted hover:text-foreground',
                        )}
                        aria-label={`Reorder ${item.name}`}
                        disabled={disabled}
                        {...attributes}
                        {...listeners}
                    >
                        <GripVertical className="h-4 w-4" />
                    </button>
                }
                actions={actions}
            />
        </div>
    );
}

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
                pinRank: undefined,
                sortOrder: undefined,
                isUncategorized: false,
            } satisfies CategorySidebarItem),
        uncategorizedItem: visibleItems.find((item) => item.isUncategorized) ?? null,
        pinnedItems: visibleItems.filter((item) => item.canEdit && item.isPinned && !item.isUncategorized),
        visibleItems: visibleItems.filter((item) => item.canEdit && !item.isPinned && !item.isUncategorized),
        hiddenItems: (categories?.hiddenItems ?? []).filter((item) => item.canEdit && !item.isUncategorized),
    };
}

function buildSnapshot(
    pinnedItems: CategorySidebarItem[],
    visibleItems: CategorySidebarItem[],
    hiddenItems: CategorySidebarItem[],
): CategorySidebarPreferencesSnapshot {
    return {
        pinnedIds: pinnedItems.map((item) => item.id),
        visibleIds: [...pinnedItems, ...visibleItems].map((item) => item.id),
        hiddenIds: hiddenItems.map((item) => item.id),
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

function firstVisibleCategoryId(selectedCategory: string | null, nextCategory: string | null) {
    return selectedCategory === nextCategory ? null : nextCategory;
}

function iconButtonClass(active = false) {
    return cn(
        'rounded-md border p-2 text-muted-foreground transition-colors',
        active ? 'border-primary/40 bg-primary/10 text-primary' : 'hover:bg-muted hover:text-foreground',
    );
}

export default function CategorySidebar({
    title,
    categories,
    selectedCategory,
    onSelectCategory,
    error,
    onRetryCategories,
    onSavePreferences,
    onResetPreferences,
}: CategorySidebarProps) {
    const [isMobileSheetOpen, setIsMobileSheetOpen] = useState(false);
    const [mobileView, setMobileView] = useState<'browse' | 'manage'>('browse');
    const [isDesktopManageOpen, setIsDesktopManageOpen] = useState(false);
    const [isHiddenSectionOpen, setIsHiddenSectionOpen] = useState(false);
    const [feedback, setFeedback] = useState<string | null>(null);
    const [isSaving, setIsSaving] = useState(false);
    const [activeDragItem, setActiveDragItem] = useState<CategorySidebarItem | null>(null);
    const [pinnedItems, setPinnedItems] = useState<CategorySidebarItem[]>([]);
    const [visibleItems, setVisibleItems] = useState<CategorySidebarItem[]>([]);
    const [hiddenItems, setHiddenItems] = useState<CategorySidebarItem[]>([]);

    const canManage = typeof onSavePreferences === 'function';
    const hasCategories = (categories?.visibleItems.length ?? 0) > 0;
    const pinLimit = categories?.pinLimit ?? 5;

    const { allCategoriesItem, uncategorizedItem } = useMemo(() => buildEditableGroups(categories), [categories]);

    useEffect(() => {
        const nextGroups = buildEditableGroups(categories);

        setPinnedItems(nextGroups.pinnedItems);
        setVisibleItems(nextGroups.visibleItems);
        setHiddenItems(nextGroups.hiddenItems);
        setFeedback(null);
    }, [categories]);

    useEffect(() => {
        if (categories?.selectedCategoryIsHidden || (pinnedItems.length === 0 && visibleItems.length === 0 && hiddenItems.length > 0)) {
            setIsHiddenSectionOpen(true);
        }
    }, [categories?.selectedCategoryIsHidden, hiddenItems.length, pinnedItems.length, visibleItems.length]);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const runSave = (
        nextPinnedItems: CategorySidebarItem[],
        nextVisibleItems: CategorySidebarItem[],
        nextHiddenItems: CategorySidebarItem[],
    ) => {
        const previousPinnedItems = pinnedItems;
        const previousVisibleItems = visibleItems;
        const previousHiddenItems = hiddenItems;

        setPinnedItems(nextPinnedItems);
        setVisibleItems(nextVisibleItems);
        setHiddenItems(nextHiddenItems);
        setFeedback(null);

        if (!onSavePreferences) {
            return;
        }

        setIsSaving(true);
        onSavePreferences(buildSnapshot(nextPinnedItems, nextVisibleItems, nextHiddenItems), {
            onError: (message) => {
                setPinnedItems(previousPinnedItems);
                setVisibleItems(previousVisibleItems);
                setHiddenItems(previousHiddenItems);
                setFeedback(message);
            },
            onFinish: () => {
                setIsSaving(false);
            },
        });
    };

    const handleResetPreferences = () => {
        if (!onResetPreferences || isSaving) {
            return;
        }

        setFeedback(null);
        setIsSaving(true);

        onResetPreferences({
            onError: (message) => {
                setFeedback(message);
            },
            onFinish: () => {
                setIsSaving(false);
            },
        });
    };

    const handleTogglePin = (item: CategorySidebarItem) => {
        if (isSaving) {
            return;
        }

        if (item.isPinned) {
            const nextPinnedItems = pinnedItems.filter((entry) => entry.id !== item.id);
            const nextVisibleItems = orderBySortOrder([...visibleItems, { ...item, isPinned: false, pinRank: undefined }]);
            runSave(nextPinnedItems, nextVisibleItems, hiddenItems);

            return;
        }

        if (pinnedItems.length >= pinLimit) {
            setFeedback(`You can pin up to ${pinLimit} categories. Unpin one before adding another.`);
            return;
        }

        const nextVisibleItems = visibleItems.filter((entry) => entry.id !== item.id);
        const nextPinnedItems = [...pinnedItems, { ...item, isPinned: true }];
        runSave(nextPinnedItems, nextVisibleItems, hiddenItems);
    };

    const handleHide = (item: CategorySidebarItem) => {
        if (isSaving) {
            return;
        }

        const nextPinnedItems = pinnedItems.filter((entry) => entry.id !== item.id);
        const nextVisibleItems = visibleItems.filter((entry) => entry.id !== item.id);
        const nextHiddenItems = orderBySortOrder([
            ...hiddenItems,
            { ...item, isPinned: false, isHidden: true, pinRank: undefined },
        ]);

        runSave(nextPinnedItems, nextVisibleItems, nextHiddenItems);
    };

    const handleUnhide = (item: CategorySidebarItem) => {
        if (isSaving) {
            return;
        }

        const nextHiddenItems = hiddenItems.filter((entry) => entry.id !== item.id);
        const nextVisibleItems = orderBySortOrder([...visibleItems, { ...item, isHidden: false, isPinned: false, pinRank: undefined }]);

        runSave(pinnedItems, nextVisibleItems, nextHiddenItems);
    };

    const handleDragStart = (event: DragStartEvent) => {
        const nextItem = [...pinnedItems, ...visibleItems].find((item) => item.id === String(event.active.id)) ?? null;
        setActiveDragItem(nextItem);
    };

    const handleDragEnd = (group: 'pinned' | 'visible', event: DragEndEvent) => {
        setActiveDragItem(null);

        const { active, over } = event;

        if (!over || active.id === over.id || isSaving) {
            return;
        }

        if (group === 'pinned') {
            const oldIndex = pinnedItems.findIndex((item) => item.id === active.id);
            const newIndex = pinnedItems.findIndex((item) => item.id === over.id);

            if (oldIndex < 0 || newIndex < 0) {
                return;
            }

            runSave(arrayMove(pinnedItems, oldIndex, newIndex), visibleItems, hiddenItems);

            return;
        }

        const oldIndex = visibleItems.findIndex((item) => item.id === active.id);
        const newIndex = visibleItems.findIndex((item) => item.id === over.id);

        if (oldIndex < 0 || newIndex < 0) {
            return;
        }

        runSave(pinnedItems, arrayMove(visibleItems, oldIndex, newIndex), hiddenItems);
    };

    const handleMobileOpenChange = (nextOpen: boolean) => {
        setIsMobileSheetOpen(nextOpen);

        if (!nextOpen) {
            setMobileView('browse');
        }
    };

    const selectCategoryAndClose = (nextCategory: string | null) => {
        setIsMobileSheetOpen(false);
        setMobileView('browse');
        onSelectCategory(firstVisibleCategoryId(selectedCategory, nextCategory));
    };

    const renderNavigationButton = (item: CategorySidebarItem, onSelect: () => void) => {
        const isSelected = selectedCategory === item.id || (item.id === ALL_CATEGORIES_ID && selectedCategory === null);
        const buttonClassName = cn(
            'w-full truncate rounded-md border px-3 py-2 text-left text-sm transition-colors',
            isSelected ? 'border-primary bg-primary/10 text-primary' : 'border-transparent hover:border-border hover:bg-muted/60',
            !item.canNavigate && 'cursor-not-allowed text-muted-foreground opacity-70',
        );

        if (item.canNavigate) {
            return (
                <button type="button" className={buttonClassName} onClick={onSelect}>
                    <span className="block truncate">{item.name}</span>
                </button>
            );
        }

        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <span className="block" tabIndex={0}>
                        <button type="button" className={buttonClassName} disabled>
                            <span className="block truncate">{item.name}</span>
                        </button>
                    </span>
                </TooltipTrigger>
                <TooltipContent>No items currently</TooltipContent>
            </Tooltip>
        );
    };

    const renderVisibleBrowseRow = (item: CategorySidebarItem, isMobile = false) => {
        const isSelected = selectedCategory === item.id;

        return (
            <div key={item.id} className="group flex items-center gap-2 rounded-md">
                <div className="min-w-0 flex-1">
                    {renderNavigationButton(item, () => selectCategoryAndClose(isSelected ? null : item.id))}
                </div>
                {item.canEdit ? (
                    <div className="flex items-center gap-1 md:opacity-0 md:transition-opacity md:group-hover:opacity-100 md:group-focus-within:opacity-100">
                        <button
                            type="button"
                            className={iconButtonClass(item.isPinned)}
                            onClick={() => handleTogglePin(item)}
                            disabled={isSaving}
                            aria-label={item.isPinned ? `Unpin ${item.name}` : `Pin ${item.name}`}
                            title={item.isPinned ? 'Unpin category' : 'Pin category'}
                        >
                            {item.isPinned ? <PinOff className="h-4 w-4" /> : <Pin className="h-4 w-4" />}
                        </button>
                        <button
                            type="button"
                            className={iconButtonClass()}
                            onClick={() => handleHide(item)}
                            disabled={isSaving}
                            aria-label={`Hide ${item.name}`}
                            title="Hide category"
                        >
                            <EyeOff className="h-4 w-4" />
                        </button>
                        {isMobile ? null : <span className="sr-only">Manage {item.name}</span>}
                    </div>
                ) : null}
            </div>
        );
    };

    const renderManageActions = (item: CategorySidebarItem, isHidden = false) => (
        <div className="flex items-center gap-1">
            {isHidden ? (
                <button
                    type="button"
                    className={iconButtonClass()}
                    onClick={() => handleUnhide(item)}
                    disabled={isSaving}
                    aria-label={`Unhide ${item.name}`}
                    title="Unhide category"
                >
                    <Eye className="h-4 w-4" />
                </button>
            ) : (
                <>
                    <button
                        type="button"
                        className={iconButtonClass(item.isPinned)}
                        onClick={() => handleTogglePin(item)}
                        disabled={isSaving}
                        aria-label={item.isPinned ? `Unpin ${item.name}` : `Pin ${item.name}`}
                        title={item.isPinned ? 'Unpin category' : 'Pin category'}
                    >
                        {item.isPinned ? <PinOff className="h-4 w-4" /> : <Pin className="h-4 w-4" />}
                    </button>
                    <button
                        type="button"
                        className={iconButtonClass()}
                        onClick={() => handleHide(item)}
                        disabled={isSaving}
                        aria-label={`Hide ${item.name}`}
                        title="Hide category"
                    >
                        <EyeOff className="h-4 w-4" />
                    </button>
                </>
            )}
        </div>
    );

    const renderManageGroup = (title: string, items: CategorySidebarItem[], group: 'pinned' | 'visible') => {
        if (items.length === 0) {
            return null;
        }

        return (
            <div className="space-y-2">
                <div className="flex items-center justify-between">
                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{title}</p>
                    <span className="text-xs text-muted-foreground">{items.length}</span>
                </div>
                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragStart={handleDragStart}
                    onDragEnd={(event) => handleDragEnd(group, event)}
                    onDragCancel={() => setActiveDragItem(null)}
                >
                    <SortableContext items={items.map((item) => item.id)} strategy={verticalListSortingStrategy}>
                        <div className="space-y-2">
                            {items.map((item) => (
                                <SortableManageCategoryRow
                                    key={item.id}
                                    item={item}
                                    disabled={isSaving}
                                    actions={renderManageActions(item)}
                                />
                            ))}
                        </div>
                    </SortableContext>
                </DndContext>
            </div>
        );
    };

    const renderManagePanel = (isMobile = false) => (
        <div className="space-y-4">
            {feedback ? (
                <div className="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">{feedback}</div>
            ) : null}

            {isSaving ? (
                <div className="rounded-md border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">Saving changes…</div>
            ) : null}

            <div className="flex flex-col gap-3 rounded-lg border bg-muted/20 p-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm font-medium">Manage categories</p>
                    <p className="text-xs text-muted-foreground">Pin up to {pinLimit}, drag to reorder, and hide categories per media type.</p>
                </div>
                <Button type="button" variant="outline" onClick={handleResetPreferences} disabled={!categories?.canReset || isSaving}>
                    <RotateCcw className="mr-2 h-4 w-4" />
                    Reset to default
                </Button>
            </div>

            <div className="space-y-2">
                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Fixed rows</p>
                <ManageCategoryRow item={allCategoriesItem} fixedLabel="Always first" />
            </div>

            {renderManageGroup('Pinned', pinnedItems, 'pinned')}
            {renderManageGroup('Visible', visibleItems, 'visible')}

            {pinnedItems.length === 0 && visibleItems.length === 0 ? (
                <div className="rounded-md border border-dashed px-3 py-4 text-sm text-muted-foreground">
                    All editable categories are hidden. Expand the hidden section below or reset to restore the default list.
                </div>
            ) : null}

            {uncategorizedItem ? (
                <div className="space-y-2">
                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Fixed footer</p>
                    <ManageCategoryRow item={uncategorizedItem} fixedLabel="Always last" muted={!uncategorizedItem.canNavigate} />
                </div>
            ) : null}

            <Collapsible open={isHiddenSectionOpen} onOpenChange={setIsHiddenSectionOpen}>
                <div className="rounded-lg border">
                    <CollapsibleTrigger asChild>
                        <button
                            type="button"
                            className="flex w-full items-center justify-between px-3 py-3 text-left text-sm font-medium"
                        >
                            <span>Hidden categories ({hiddenItems.length})</span>
                            {isHiddenSectionOpen ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                        </button>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <div className="space-y-2 border-t px-3 py-3">
                            {hiddenItems.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No hidden categories.</p>
                            ) : (
                                hiddenItems.map((item) => (
                                    <ManageCategoryRow key={item.id} item={item} actions={renderManageActions(item, true)} muted={!item.canNavigate} />
                                ))
                            )}
                        </div>
                    </CollapsibleContent>
                </div>
            </Collapsible>

            {isMobile ? null : (
                <Button type="button" variant="outline" className="w-full" onClick={() => setIsDesktopManageOpen(false)}>
                    Done managing
                </Button>
            )}
        </div>
    );

    const renderBrowsePanel = (isMobile = false) => (
        <div className="space-y-3">
            {error ? (
                <div className="rounded-md border border-destructive/40 bg-destructive/10 p-3">
                    <p className="text-sm font-medium text-destructive">{error}</p>
                    <Button type="button" className="mt-3 w-full" onClick={onRetryCategories}>
                        Retry categories
                    </Button>
                </div>
            ) : null}

            {!hasCategories ? (
                <EmptyState
                    title="Categories unavailable"
                    description="We couldn't load categories right now."
                    className="py-8"
                    action={
                        <Button type="button" onClick={onRetryCategories}>
                            Retry categories
                        </Button>
                    }
                />
            ) : (
                <>
                    {feedback ? (
                        <div className="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">{feedback}</div>
                    ) : null}

                    {canManage ? (
                        <div className="flex items-center justify-between rounded-lg border bg-muted/20 px-3 py-2">
                            <div>
                                <p className="text-sm font-medium">Browse-first controls</p>
                                <p className="text-xs text-muted-foreground">Use row actions for quick edits or manage mode for reordering.</p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    if (isMobile) {
                                        setMobileView('manage');
                                        return;
                                    }

                                    setIsDesktopManageOpen(true);
                                }}
                                disabled={isSaving}
                            >
                                <Settings2 className="mr-2 h-4 w-4" />
                                Manage
                            </Button>
                        </div>
                    ) : null}

                    <div className="space-y-2">
                        <div>{renderNavigationButton(allCategoriesItem, () => selectCategoryAndClose(null))}</div>
                        {pinnedItems.map((item) => renderVisibleBrowseRow(item, isMobile))}
                        {visibleItems.map((item) => renderVisibleBrowseRow(item, isMobile))}
                        {uncategorizedItem ? <div>{renderNavigationButton(uncategorizedItem, () => selectCategoryAndClose(uncategorizedItem.id))}</div> : null}
                    </div>
                </>
            )}
        </div>
    );

    return (
        <>
            <aside className="hidden w-72 shrink-0 md:block">
                <div className="sticky top-6 rounded-lg border bg-card p-4">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <h2 className="text-sm font-semibold tracking-wide uppercase">{title}</h2>
                        {canManage && isDesktopManageOpen ? (
                            <Button type="button" variant="ghost" size="sm" onClick={() => setIsDesktopManageOpen(false)}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Browse
                            </Button>
                        ) : null}
                    </div>
                    {isDesktopManageOpen ? renderManagePanel() : renderBrowsePanel()}
                </div>
            </aside>

            <div className="md:hidden">
                <Sheet open={isMobileSheetOpen} onOpenChange={handleMobileOpenChange}>
                    <SheetTrigger asChild>
                        <Button type="button" variant="outline" className="w-full justify-start gap-2">
                            <ListFilterIcon className="h-4 w-4" />
                            {title}
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="bottom" className="h-[85vh] w-full max-w-none overflow-hidden rounded-t-xl p-0">
                        <SheetHeader>
                            <SheetTitle>{mobileView === 'manage' ? `${title} Manager` : title}</SheetTitle>
                        </SheetHeader>
                        <div className="flex items-center justify-between border-b px-4 pb-4">
                            {mobileView === 'manage' ? (
                                <Button type="button" variant="ghost" size="sm" onClick={() => setMobileView('browse')}>
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    Browse categories
                                </Button>
                            ) : (
                                <span className="text-sm text-muted-foreground">Choose a category or open manage mode.</span>
                            )}

                            {mobileView === 'browse' && canManage ? (
                                <Button type="button" variant="outline" size="sm" onClick={() => setMobileView('manage')} disabled={isSaving}>
                                    <Settings2 className="mr-2 h-4 w-4" />
                                    Manage
                                </Button>
                            ) : null}
                        </div>
                        <div className="flex-1 overflow-y-auto px-4 pb-6">{mobileView === 'manage' ? renderManagePanel(true) : renderBrowsePanel(true)}</div>
                    </SheetContent>
                </Sheet>
            </div>

            <DragOverlay>{activeDragItem ? <ManageCategoryRow item={activeDragItem} actions={renderManageActions(activeDragItem, activeDragItem.isHidden)} /> : null}</DragOverlay>
        </>
    );
}
