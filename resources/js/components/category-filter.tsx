import { Category } from '@/types/category';
import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';

interface CategoryFilterProps {
    categories: Category[];
    currentCategory: Category | null;
    baseUrl: string;
}

export default function CategoryFilter({ categories, currentCategory, baseUrl }: CategoryFilterProps) {
    if (!categories || categories.length === 0) return null;

    return (
        <div className="w-full">
            <ScrollArea className="w-full whitespace-nowrap rounded-md border p-4">
                <div className="flex w-max space-x-2">
                    <Link
                        href={baseUrl}
                        preserveState
                        preserveScroll
                        className={cn(
                            "inline-flex h-9 items-center justify-center rounded-md px-4 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring",
                            !currentCategory
                                ? "bg-primary text-primary-foreground shadow hover:bg-primary/90"
                                : "bg-transparent"
                        )}
                    >
                        All
                    </Link>
                    {categories.map((category) => (
                        <Link
                            key={category.id}
                            href={`${baseUrl}?category=${category.id}`}
                            preserveState
                            preserveScroll
                            className={cn(
                                "inline-flex h-9 items-center justify-center rounded-md px-4 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring",
                                currentCategory?.id === category.id
                                    ? "bg-primary text-primary-foreground shadow hover:bg-primary/90"
                                    : "bg-transparent"
                            )}
                        >
                            {category.name}
                        </Link>
                    ))}
                </div>
                <ScrollBar orientation="horizontal" />
            </ScrollArea>
        </div>
    );
}
