import EmptyState from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { EyeOff, Pin, PinOff, Settings2 } from 'lucide-react';
import { iconButtonClass } from './shared-ui';
import { CategorySidebarItem, CategorySidebarProps } from './types';

interface CategorySidebarBrowseProps extends CategorySidebarProps {
    pinnedItems: CategorySidebarItem[];
    visibleItems: CategorySidebarItem[];
    allCategoriesItem: CategorySidebarItem;
    uncategorizedItem: CategorySidebarItem | null;
    isMobile?: boolean;
    isSaving: boolean;
    canManage: boolean;
    onTogglePin: (item: CategorySidebarItem) => void;
    onHide: (item: CategorySidebarItem) => void;
    onManage: () => void;
}

export function CategorySidebarBrowse({
    categories,
    selectedCategory,
    onSelectCategory,
    error,
    onRetryCategories,
    pinnedItems,
    visibleItems,
    allCategoriesItem,
    uncategorizedItem,
    isSaving,
    canManage,
    onTogglePin,
    onHide,
    onManage,
}: CategorySidebarBrowseProps) {
    const hasCategories = (categories?.visibleItems.length ?? 0) > 0;

    const renderNavigationButton = (item: CategorySidebarItem, onSelect: () => void) => {
        const ALL_CATEGORIES_ID = 'all-categories';
        const isSelected = selectedCategory === item.id || (item.id === ALL_CATEGORIES_ID && selectedCategory === null);
        const buttonClassName = cn(
            'group flex w-full items-start gap-2 rounded-md border px-3 py-2 text-left text-sm transition-all duration-200',
            isSelected
                ? 'border-primary/50 bg-primary/5 text-primary ring-1 ring-primary/20'
                : 'border-transparent hover:border-border hover:bg-muted/80',
            !item.canNavigate && 'cursor-not-allowed text-muted-foreground opacity-60',
        );

        if (item.canNavigate) {
            return (
                <button type="button" className={buttonClassName} onClick={onSelect}>
                    <span className="block whitespace-normal break-words font-medium leading-5">{item.name}</span>
                </button>
            );
        }

        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <span className="block" tabIndex={0}>
                        <button type="button" className={buttonClassName} disabled>
                            <span className="block whitespace-normal break-words font-medium leading-5">{item.name}</span>
                        </button>
                    </span>
                </TooltipTrigger>
                <TooltipContent>No items currently</TooltipContent>
            </Tooltip>
        );
    };

    const renderVisibleBrowseRow = (item: CategorySidebarItem) => {
        const isSelected = selectedCategory === item.id;

        return (
            <div key={item.id} className="group/row flex items-start gap-1.5">
                <div className="min-w-0 flex-1">
                    {renderNavigationButton(item, () => onSelectCategory(isSelected ? null : item.id))}
                </div>
                {item.canEdit && (
                    <div className="flex items-center gap-1 opacity-0 transition-opacity focus-within:opacity-100 group-hover/row:opacity-100 pt-0.5">
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <button
                                    type="button"
                                    className={iconButtonClass(item.isPinned)}
                                    onClick={() => onTogglePin(item)}
                                    disabled={isSaving}
                                    aria-label={item.isPinned ? `Unpin ${item.name}` : `Pin ${item.name}`}
                                >
                                    {item.isPinned ? <PinOff className="h-3.5 w-3.5" /> : <Pin className="h-3.5 w-3.5" />}
                                </button>
                            </TooltipTrigger>
                            <TooltipContent>{item.isPinned ? 'Unpin category' : 'Pin category'}</TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <button
                                    type="button"
                                    className={iconButtonClass()}
                                    onClick={() => onHide(item)}
                                    disabled={isSaving}
                                    aria-label={`Hide ${item.name}`}
                                >
                                    <EyeOff className="h-3.5 w-3.5" />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent>Hide category</TooltipContent>
                        </Tooltip>
                    </div>
                )}
            </div>
        );
    };

    if (error) {
        return (
            <div className="rounded-lg border border-destructive/20 bg-destructive/5 p-4 text-center">
                <p className="mb-3 text-sm font-medium text-destructive">{error}</p>
                <Button type="button" size="sm" variant="outline" className="w-full" onClick={onRetryCategories}>
                    Retry categories
                </Button>
            </div>
        );
    }

    if (!hasCategories) {
        return (
            <EmptyState
                title="No categories"
                description="We couldn't find any categories for this media."
                className="py-10"
                action={
                    <Button type="button" size="sm" onClick={onRetryCategories}>
                        Retry
                    </Button>
                }
            />
        );
    }

    return (
        <div className="space-y-4">
            {canManage && (
                <div className="group relative flex items-center justify-between rounded-lg border bg-muted/30 p-3 transition-colors hover:bg-muted/50">
                    <div className="min-w-0 flex-1 pr-2">
                        <p className="text-sm font-semibold">Custom View</p>
                        <p className="text-xs text-muted-foreground">Adjust categories to your preference.</p>
                    </div>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        className="h-8 shadow-sm"
                        onClick={onManage}
                        disabled={isSaving}
                    >
                        <Settings2 className="mr-1.5 h-3.5 w-3.5" />
                        Manage
                    </Button>
                </div>
            )}

            <div className="space-y-1.5">
                <div>{renderNavigationButton(allCategoriesItem, () => onSelectCategory(null))}</div>
                {pinnedItems.map((item) => renderVisibleBrowseRow(item))}
                {visibleItems.map((item) => renderVisibleBrowseRow(item))}
                {uncategorizedItem && (
                    <div>{renderNavigationButton(uncategorizedItem, () => onSelectCategory(uncategorizedItem.id))}</div>
                )}
            </div>
        </div>
    );
}
