import { scrollToTop } from '@/lib/scroll-utils';
import type { PendingVisit } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import {
    type CategorySidebarData,
    type CategorySidebarItem,
    type CategorySidebarMutationOptions,
    type CategorySidebarPreferencesSnapshot,
} from '@/components/category-sidebar';

export const CATEGORY_LOAD_ERROR_MESSAGE = 'Unable to load categories right now. Please try again.';
export const CATEGORY_PREFERENCE_ERROR_MESSAGE = 'Unable to save your category changes right now. Please try again.';

function firstCategoryPreferenceError(errors: Record<string, string>) {
    return errors.pinned_ids ?? errors.visible_ids ?? errors.hidden_ids ?? errors.ignored_ids ?? CATEGORY_PREFERENCE_ERROR_MESSAGE;
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

function buildPreferenceSnapshot(categories: CategorySidebarData | null | undefined) {
    const editableVisibleItems = (categories?.visibleItems ?? []).filter((item) => item.canEdit && !item.isUncategorized);
    const pinnedItems = orderByPinRank(editableVisibleItems.filter((item) => item.isPinned && !item.isIgnored));
    const visibleItems = orderBySortOrder(editableVisibleItems.filter((item) => !item.isPinned && !item.isIgnored));
    const ignoredItems = orderBySortOrder(editableVisibleItems.filter((item) => item.isIgnored));
    const hiddenItems = orderBySortOrder((categories?.hiddenItems ?? []).filter((item) => item.canEdit && !item.isUncategorized));

    return {
        pinnedItems,
        visibleItems,
        ignoredItems,
        hiddenItems,
    };
}

interface UseCategoryBrowserOptions {
    routeName: string;
    mediaType: 'movie' | 'series';
    only: string[];
}

export function useCategoryBrowser({ routeName, mediaType, only }: UseCategoryBrowserOptions) {
    const [isSwitchingCategory, setIsSwitchingCategory] = useState(false);
    const [categoryLoadError, setCategoryLoadError] = useState<string | null>(null);
    const [manageRequestKey, setManageRequestKey] = useState(0);
    const categoryVisitCancelToken = useRef<{ cancel: () => void } | null>(null);

    const handleCategoryVisitFinish = (visit: PendingVisit) => {
        setIsSwitchingCategory(false);
        categoryVisitCancelToken.current = null;

        if (!visit.completed && !visit.cancelled && !visit.interrupted) {
            setCategoryLoadError(CATEGORY_LOAD_ERROR_MESSAGE);
        }
    };

    useEffect(() => {
        return () => {
            categoryVisitCancelToken.current?.cancel();
            categoryVisitCancelToken.current = null;
        };
    }, []);

    const handleSelectCategory = (nextCategory: string | null, currentCategory: string | null) => {
        const category = nextCategory === currentCategory ? null : nextCategory;
        categoryVisitCancelToken.current?.cancel();

        scrollToTop('smooth');

        router.visit(category ? route(routeName, { category }) : route(routeName), {
            method: 'get',
            only,
            preserveState: true,
            preserveScroll: false,
            onCancelToken: (token) => {
                categoryVisitCancelToken.current = token as { cancel: () => void };
            },
            onStart: () => {
                setIsSwitchingCategory(true);
                setCategoryLoadError(null);
            },
            onSuccess: () => {
                setCategoryLoadError(null);
            },
            onError: () => {
                setCategoryLoadError(CATEGORY_LOAD_ERROR_MESSAGE);
                setIsSwitchingCategory(false);
            },
            onFinish: handleCategoryVisitFinish,
        });
    };

    const handleRetryCategories = () => {
        scrollToTop('instant');

        router.reload({
            only,
            onCancelToken: (token) => {
                categoryVisitCancelToken.current = token as { cancel: () => void };
            },
            onStart: () => {
                setIsSwitchingCategory(true);
                setCategoryLoadError(null);
            },
            onSuccess: () => {
                setCategoryLoadError(null);
            },
            onError: () => {
                setCategoryLoadError(CATEGORY_LOAD_ERROR_MESSAGE);
                setIsSwitchingCategory(false);
            },
            onFinish: handleCategoryVisitFinish,
        });
    };

    const handleSavePreferences = (
        payload: CategorySidebarPreferencesSnapshot,
        options?: CategorySidebarMutationOptions,
    ) => {
        router.patch(
            route('category-preferences.update', { mediaType }),
            {
                pinned_ids: payload.pinnedIds,
                visible_ids: payload.visibleIds,
                hidden_ids: payload.hiddenIds,
            },
            {
                only: ['categories', 'filters'],
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    options?.onSuccess?.();
                },
                onError: (errors) => {
                    options?.onError?.(firstCategoryPreferenceError(errors as Record<string, string>));
                },
                onFinish: () => {
                    options?.onFinish?.();
                },
            },
        );
    };

    const handleResetPreferences = (options?: CategorySidebarMutationOptions) => {
        router.delete(route('category-preferences.reset', { mediaType }), {
            only: ['categories', 'filters'],
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                options?.onSuccess?.();
            },
            onError: () => {
                options?.onError?.(CATEGORY_PREFERENCE_ERROR_MESSAGE);
            },
            onFinish: () => {
                options?.onFinish?.();
            },
        });
    };

    const handleUnignoreCategory = (
        categoryId: string | null | undefined,
        categories: CategorySidebarData | null | undefined,
        options?: CategorySidebarMutationOptions,
    ) => {
        if (!categoryId || !categories) {
            options?.onError?.(CATEGORY_PREFERENCE_ERROR_MESSAGE);
            options?.onFinish?.();

            return;
        }

        const { pinnedItems, visibleItems, ignoredItems, hiddenItems } = buildPreferenceSnapshot(categories);
        const categoryToRestore = ignoredItems.find((item) => item.id === categoryId);

        if (!categoryToRestore) {
            options?.onSuccess?.();
            options?.onFinish?.();

            return;
        }

        const nextIgnoredItems = ignoredItems.filter((item) => item.id !== categoryId);
        const nextPinnedItems = categoryToRestore.isPinned
            ? orderByPinRank([...pinnedItems, { ...categoryToRestore, isIgnored: false }])
            : pinnedItems;
        const nextVisibleItems = categoryToRestore.isPinned
            ? visibleItems
            : orderBySortOrder([...visibleItems, { ...categoryToRestore, isIgnored: false }]);

        handleSavePreferences(
            {
                pinnedIds: nextPinnedItems.map((item) => item.id),
                visibleIds: [...nextPinnedItems, ...nextVisibleItems].map((item) => item.id),
                hiddenIds: hiddenItems.map((item) => item.id),
                ignoredIds: nextIgnoredItems.map((item) => item.id),
            },
            options,
        );
    };

    const requestManageMode = () => {
        setManageRequestKey((current) => current + 1);
    };

    return {
        isSwitchingCategory,
        categoryLoadError,
        manageRequestKey,
        handleSelectCategory,
        handleRetryCategories,
        handleSavePreferences,
        handleResetPreferences,
        handleUnignoreCategory,
        requestManageMode,
    };
}
