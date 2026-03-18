import { scrollToTop } from '@/lib/scroll-utils';
import type { PendingVisit } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import {
    type CategorySidebarMutationOptions,
    type CategorySidebarPreferencesSnapshot,
} from '@/components/category-sidebar';

export const CATEGORY_LOAD_ERROR_MESSAGE = 'Unable to load categories right now. Please try again.';
export const CATEGORY_PREFERENCE_ERROR_MESSAGE = 'Unable to save your category changes right now. Please try again.';

function firstCategoryPreferenceError(errors: Record<string, string>) {
    return errors.pinned_ids ?? errors.visible_ids ?? errors.hidden_ids ?? CATEGORY_PREFERENCE_ERROR_MESSAGE;
}

interface UseCategoryBrowserOptions {
    routeName: string;
    mediaType: 'movie' | 'series';
    only: string[];
}

export function useCategoryBrowser({ routeName, mediaType, only }: UseCategoryBrowserOptions) {
    const [isSwitchingCategory, setIsSwitchingCategory] = useState(false);
    const [categoryLoadError, setCategoryLoadError] = useState<string | null>(null);
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

    return {
        isSwitchingCategory,
        categoryLoadError,
        handleSelectCategory,
        handleRetryCategories,
        handleSavePreferences,
        handleResetPreferences,
    };
}
