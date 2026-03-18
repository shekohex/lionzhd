import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import {
    closestCenter,
    DndContext,
    DragOverlay,
    DragEndEvent,
    DragStartEvent,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { ChevronDown, ChevronUp, Eye, EyeOff, GripVertical, Pin, PinOff, RotateCcw } from 'lucide-react';
import { ReactNode, useEffect, useState } from 'react';
import { iconButtonClass, ManageCategoryRow } from './shared-ui';
import { CategorySidebarItem, CategorySidebarProps } from './types';

interface CategorySidebarManageProps extends CategorySidebarProps {
    pinnedItems: CategorySidebarItem[];
    visibleItems: CategorySidebarItem[];
    hiddenItems: CategorySidebarItem[];
    allCategoriesItem: CategorySidebarItem;
    uncategorizedItem: CategorySidebarItem | null;
    isMobile?: boolean;
    isSaving: boolean;
    feedback: string | null;
    pinLimit: number;
    onTogglePin: (item: CategorySidebarItem) => void;
    onHide: (item: CategorySidebarItem) => void;
    onUnhide: (item: CategorySidebarItem) => void;
    onReorder: (group: 'pinned' | 'visible', nextItems: CategorySidebarItem[]) => void;
    onReset: () => void;
    onDone: () => void;
}

function SortableManageCategoryRow({
    item,
    disabled,
    actions,
    active = false,
}: {
    item: CategorySidebarItem;
    disabled: boolean;
    actions: ReactNode;
    active?: boolean;
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
            className={cn(isDragging && 'z-50 opacity-40')}
        >
            <ManageCategoryRow
                item={item}
                muted={disabled}
                active={active}
                dragHandle={
                    <button
                        type="button"
                        className={cn(
                            'rounded-md border p-2 text-muted-foreground transition-all duration-200 focus-visible:ring-2 focus-visible:ring-primary',
                            disabled ? 'cursor-not-allowed opacity-50' : 'hover:bg-muted hover:text-foreground cursor-grab active:cursor-grabbing',
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

export function CategorySidebarManage({
    categories,
    pinnedItems,
    visibleItems,
    hiddenItems,
    allCategoriesItem,
    uncategorizedItem,
    isMobile = false,
    isSaving,
    feedback,
    pinLimit,
    onTogglePin,
    onHide,
    onUnhide,
    onReorder,
    onReset,
    onDone,
    selectedCategory,
}: CategorySidebarManageProps) {
    const [activeDragItem, setActiveDragItem] = useState<CategorySidebarItem | null>(null);
    const [activeGroup, setActiveGroup] = useState<'pinned' | 'visible' | null>(null);
    const [isHiddenSectionOpen, setIsHiddenSectionOpen] = useState(hiddenItems.some((item) => item.id === selectedCategory));

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    useEffect(() => {
        if (hiddenItems.some((item) => item.id === selectedCategory)) {
            setIsHiddenSectionOpen(true);
        }
    }, [hiddenItems, selectedCategory]);

    const handleDragStart = (event: DragStartEvent) => {
        const activeId = String(event.active.id);
        const pinnedItem = pinnedItems.find((item) => item.id === activeId);

        if (pinnedItem) {
            setActiveGroup('pinned');
            setActiveDragItem(pinnedItem);

            return;
        }

        const visibleItem = visibleItems.find((item) => item.id === activeId) ?? null;
        setActiveGroup(visibleItem ? 'visible' : null);
        setActiveDragItem(visibleItem);
    };

    const handleDragEnd = (event: DragEndEvent) => {
        setActiveDragItem(null);
        const group = activeGroup;
        setActiveGroup(null);

        const { active, over } = event;
        if (!group || !over || active.id === over.id || isSaving) return;

        const items = group === 'pinned' ? pinnedItems : visibleItems;
        const oldIndex = items.findIndex((item) => item.id === active.id);
        const newIndex = items.findIndex((item) => item.id === over.id);

        if (oldIndex >= 0 && newIndex >= 0) {
            onReorder(group, arrayMove(items, oldIndex, newIndex));
        }
    };

    const renderManageActions = (item: CategorySidebarItem, isHidden = false) => (
        <div className="flex items-center gap-1.5">
            {isHidden ? (
                <button
                    type="button"
                    className={iconButtonClass()}
                    onClick={() => onUnhide(item)}
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
                        onClick={() => onTogglePin(item)}
                        disabled={isSaving}
                        aria-label={item.isPinned ? `Unpin ${item.name}` : `Pin ${item.name}`}
                        title={item.isPinned ? 'Unpin category' : 'Pin category'}
                    >
                        {item.isPinned ? <PinOff className="h-4 w-4" /> : <Pin className="h-4 w-4" />}
                    </button>
                    <button
                        type="button"
                        className={iconButtonClass()}
                        onClick={() => onHide(item)}
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
        if (items.length === 0 && group === 'pinned') return null;

        return (
            <div className="space-y-3">
                <div className="flex items-center justify-between px-1">
                    <p className="text-[10px] font-bold tracking-widest text-muted-foreground uppercase">{title}</p>
                    <span className="text-[10px] font-medium text-muted-foreground bg-muted px-1.5 py-0.5 rounded-full">{items.length}</span>
                </div>
                <SortableContext items={items.map((item) => item.id)} strategy={verticalListSortingStrategy}>
                    <div className="space-y-2">
                        {items.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground">
                                No {title.toLowerCase()} categories
                            </div>
                        ) : (
                            items.map((item) => (
                                <SortableManageCategoryRow
                                    key={item.id}
                                    item={item}
                                    disabled={isSaving}
                                    active={selectedCategory === item.id}
                                    actions={renderManageActions(item)}
                                />
                            ))
                        )}
                    </div>
                </SortableContext>
            </div>
        );
    };

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragStart={handleDragStart}
            onDragEnd={handleDragEnd}
            onDragCancel={() => {
                setActiveDragItem(null);
                setActiveGroup(null);
            }}
        >
            <div className="space-y-6">
                <div className="space-y-3">
                    {feedback && (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs font-medium text-amber-800 shadow-sm animate-in fade-in slide-in-from-top-1">
                            {feedback}
                        </div>
                    )}

                    <div className="rounded-xl border bg-gradient-to-b from-muted/30 to-muted/10 p-4 shadow-sm">
                        <div className="mb-4 space-y-1">
                            <p className="text-sm font-bold">Preferences</p>
                            <p className="text-xs leading-relaxed text-muted-foreground">
                                Pin up to {pinLimit} favorites and drag to reorder. Hidden categories won't appear in browse.
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="w-full bg-background font-medium h-9"
                            onClick={onReset}
                            disabled={!categories?.canReset || isSaving}
                        >
                            <RotateCcw className="mr-2 h-3.5 w-3.5" />
                            Reset to default
                        </Button>
                    </div>
                </div>

                <div className="space-y-2 px-1">
                    <p className="text-[10px] font-bold tracking-widest text-muted-foreground uppercase">Fixed Rows</p>
                    <ManageCategoryRow item={allCategoriesItem} fixedLabel="Always first" active={selectedCategory === null || selectedCategory === allCategoriesItem.id} />
                </div>

                <div className="space-y-5">
                    {renderManageGroup('Pinned', pinnedItems, 'pinned')}
                    {renderManageGroup('Visible', visibleItems, 'visible')}
                </div>

                {uncategorizedItem && (
                    <div className="space-y-2 px-1">
                        <p className="text-[10px] font-bold tracking-widest text-muted-foreground uppercase">Fixed Footer</p>
                        <ManageCategoryRow
                            item={uncategorizedItem}
                            fixedLabel="Always last"
                            muted={!uncategorizedItem.canNavigate}
                            active={selectedCategory === uncategorizedItem.id}
                        />
                    </div>
                )}

                <Collapsible open={isHiddenSectionOpen} onOpenChange={setIsHiddenSectionOpen} className="group/collapsible">
                    <div className="rounded-xl border transition-colors hover:border-muted-foreground/20">
                        <CollapsibleTrigger asChild>
                            <button
                                type="button"
                                className="flex w-full items-center justify-between px-4 py-3.5 text-left text-sm font-bold"
                            >
                                <span className="flex items-center gap-2">
                                    Hidden Categories
                                    <span className="bg-muted px-2 py-0.5 rounded-full text-[10px] font-medium text-muted-foreground">{hiddenItems.length}</span>
                                </span>
                                {isHiddenSectionOpen ? <ChevronUp className="h-4 w-4 text-muted-foreground" /> : <ChevronDown className="h-4 w-4 text-muted-foreground" />}
                            </button>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <div className="space-y-2 border-t bg-muted/5 px-3 py-3 animate-in fade-in slide-in-from-top-1">
                                {hiddenItems.length === 0 ? (
                                    <p className="py-2 text-center text-xs text-muted-foreground italic">No hidden categories.</p>
                                ) : (
                                    hiddenItems.map((item) => (
                                        <ManageCategoryRow
                                            key={item.id}
                                            item={item}
                                            active={selectedCategory === item.id}
                                            actions={renderManageActions(item, true)}
                                            muted={!item.canNavigate}
                                        />
                                    ))
                                )}
                            </div>
                        </CollapsibleContent>
                    </div>
                </Collapsible>

                {!isMobile && (
                    <Button type="button" className="w-full h-10 font-bold shadow-md" onClick={onDone}>
                        Save & Exit
                    </Button>
                )}
            </div>

            <DragOverlay>
                {activeDragItem ? (
                    <div className="w-64 rounded-md opacity-90 shadow-2xl ring-2 ring-primary/20">
                        <ManageCategoryRow item={activeDragItem} active />
                    </div>
                ) : null}
            </DragOverlay>
        </DndContext>
    );
}
