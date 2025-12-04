import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { router } from '@inertiajs/react';

interface CategoryFilterProps {
    categories: App.Data.CategoryData[];
    selectedCategory?: string;
}

export default function CategoryFilter({ categories, selectedCategory }: CategoryFilterProps) {
    const handleValueChange = (value: string) => {
        router.get(
            window.location.pathname,
            value === 'all' ? {} : { category: value },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true, // Use replace to avoid filling history stack
            }
        );
    };

    return (
        <Select
            value={selectedCategory ? String(selectedCategory) : 'all'}
            onValueChange={handleValueChange}
        >
            <SelectTrigger className="w-[200px]">
                <SelectValue placeholder="Select Category" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">All Categories</SelectItem>
                {categories.map((category) => (
                    <SelectItem key={category.category_id} value={String(category.category_id)}>
                        {category.category_name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
