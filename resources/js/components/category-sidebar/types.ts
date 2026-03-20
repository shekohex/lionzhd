import { type ReactNode } from 'react';

export type CategorySidebarData = App.Data.CategorySidebarData;
export type CategorySidebarItem = App.Data.CategorySidebarItemData;
export const CATEGORY_SIDEBAR_ALL_CATEGORIES_ID = 'all-categories';

export interface CategorySidebarPreferencesSnapshot {
    pinnedIds: string[];
    visibleIds: string[];
    hiddenIds: string[];
    ignoredIds: string[];
}

export interface CategorySidebarMutationOptions {
    onSuccess?: () => void;
    onError?: (message: string) => void;
    onFinish?: () => void;
}

export interface CategorySidebarProps {
    title: string;
    categories: CategorySidebarData | null | undefined;
    selectedCategory: string | null;
    desktopHeight?: number | null;
    onSelectCategory: (nextCategory: string | null) => void;
    error: string | null;
    onRetryCategories: () => void;
    onSavePreferences?: (
        snapshot: CategorySidebarPreferencesSnapshot,
        options?: CategorySidebarMutationOptions,
    ) => void;
    onResetPreferences?: (options?: CategorySidebarMutationOptions) => void;
    manageRequestKey?: number;
    className?: string;
}

export interface ManageCategoryRowProps {
    item: CategorySidebarItem;
    dragHandle?: ReactNode;
    actions?: ReactNode;
    muted?: boolean;
    fixedLabel?: string;
    active?: boolean;
}
