import EmptyState from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { ListFilterIcon } from 'lucide-react';
import { useState } from 'react';

type CategorySidebarItem = {
    id: string;
    name: string;
    disabled: boolean;
    isUncategorized: boolean;
};

interface CategorySidebarProps {
    title: string;
    categories: CategorySidebarItem[] | null | undefined;
    selectedCategory: string | null;
    onSelectCategory: (nextCategory: string | null) => void;
    error: string | null;
    onRetryCategories: () => void;
}

export default function CategorySidebar({
    title,
    categories,
    selectedCategory,
    onSelectCategory,
    error,
    onRetryCategories,
}: CategorySidebarProps) {
    const [isMobileSheetOpen, setIsMobileSheetOpen] = useState(false);
    const categoryItems = categories ?? [];
    const hasCategories = categoryItems.length > 0;

    const renderCategoryOption = ({
        id,
        label,
        selected,
        disabled,
        onSelect,
    }: {
        id: string;
        label: string;
        selected: boolean;
        disabled: boolean;
        onSelect: () => void;
    }) => {
        const buttonClassName = cn(
            'w-full truncate rounded-md border px-3 py-2 text-left text-sm transition-colors',
            selected
                ? 'border-primary bg-primary/10 text-primary'
                : 'border-transparent hover:border-border hover:bg-muted/60',
            disabled && 'text-muted-foreground cursor-not-allowed opacity-70',
        );

        if (!disabled) {
            return (
                <button key={id} type="button" className={buttonClassName} onClick={onSelect}>
                    <span className="block truncate">{label}</span>
                </button>
            );
        }

        return (
            <Tooltip key={id}>
                <TooltipTrigger asChild>
                    <span className="block" tabIndex={0}>
                        <button type="button" className={buttonClassName} disabled>
                            <span className="block truncate">{label}</span>
                        </button>
                    </span>
                </TooltipTrigger>
                <TooltipContent>No items currently</TooltipContent>
            </Tooltip>
        );
    };

    const renderCategoryList = () => (
        <div className="space-y-3">
            {error && (
                <div className="rounded-md border border-destructive/40 bg-destructive/10 p-3">
                    <p className="text-sm font-medium text-destructive">{error}</p>
                    <Button type="button" className="mt-3 w-full" onClick={onRetryCategories}>
                        Retry categories
                    </Button>
                </div>
            )}

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
                <div className="space-y-2">
                    {renderCategoryOption({
                        id: 'all-categories',
                        label: 'All categories',
                        selected: selectedCategory === null,
                        disabled: false,
                        onSelect: () => onSelectCategory(null),
                    })}

                    {categoryItems.map((category) => {
                        const isSelected = selectedCategory === category.id;

                        return renderCategoryOption({
                            id: category.id,
                            label: category.name,
                            selected: isSelected,
                            disabled: category.disabled,
                            onSelect: () => onSelectCategory(isSelected ? null : category.id),
                        });
                    })}
                </div>
            )}
        </div>
    );

    return (
        <>
            <aside className="hidden w-72 shrink-0 md:block">
                <div className="sticky top-6 rounded-lg border bg-card p-4">
                    <h2 className="mb-4 text-sm font-semibold tracking-wide uppercase">{title}</h2>
                    {renderCategoryList()}
                </div>
            </aside>

            <div className="md:hidden">
                <Sheet open={isMobileSheetOpen} onOpenChange={setIsMobileSheetOpen}>
                    <SheetTrigger asChild>
                        <Button type="button" variant="outline" className="w-full justify-start gap-2">
                            <ListFilterIcon className="h-4 w-4" />
                            {title}
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="left" className="w-[90vw] sm:max-w-sm">
                        <SheetHeader>
                            <SheetTitle>{title}</SheetTitle>
                        </SheetHeader>
                        <div className="px-4 pb-4">{renderCategoryList()}</div>
                    </SheetContent>
                </Sheet>
            </div>
        </>
    );
}
