import { useCallback, useEffect, useRef, useState, type PointerEvent as ReactPointerEvent } from 'react';

interface UseResizableSidebarOptions {
    defaultWidth?: number;
    minWidth?: number;
    maxWidth?: number;
}

export function useResizableSidebar(
    storageKey: string,
    { defaultWidth = 440, minWidth = 360, maxWidth = 640 }: UseResizableSidebarOptions = {},
) {
    const dragState = useRef<{ startX: number; startWidth: number } | null>(null);
    const [sidebarWidth, setSidebarWidth] = useState(defaultWidth);
    const [isResizing, setIsResizing] = useState(false);

    const clampWidth = useCallback(
        (value: number) => {
            if (typeof window === 'undefined') {
                return Math.min(maxWidth, Math.max(minWidth, value));
            }

            const viewportMax = Math.max(minWidth, Math.min(maxWidth, window.innerWidth - 320));

            return Math.min(viewportMax, Math.max(minWidth, value));
        },
        [maxWidth, minWidth],
    );

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const savedWidth = Number.parseInt(window.localStorage.getItem(storageKey) ?? '', 10);

        if (Number.isFinite(savedWidth)) {
            setSidebarWidth(clampWidth(savedWidth));
        } else {
            setSidebarWidth(clampWidth(defaultWidth));
        }
    }, [clampWidth, defaultWidth, storageKey]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        window.localStorage.setItem(storageKey, String(clampWidth(sidebarWidth)));
    }, [clampWidth, sidebarWidth, storageKey]);

    useEffect(() => {
        if (!isResizing || typeof window === 'undefined' || typeof document === 'undefined') {
            return;
        }

        const handlePointerMove = (event: PointerEvent) => {
            const state = dragState.current;

            if (!state) {
                return;
            }

            setSidebarWidth(clampWidth(state.startWidth + event.clientX - state.startX));
        };

        const stopResizing = () => {
            dragState.current = null;
            setIsResizing(false);
        };

        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';
        window.addEventListener('pointermove', handlePointerMove);
        window.addEventListener('pointerup', stopResizing);

        return () => {
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            window.removeEventListener('pointermove', handlePointerMove);
            window.removeEventListener('pointerup', stopResizing);
        };
    }, [clampWidth, isResizing]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const handleResize = () => {
            setSidebarWidth((currentWidth) => clampWidth(currentWidth));
        };

        window.addEventListener('resize', handleResize);

        return () => {
            window.removeEventListener('resize', handleResize);
        };
    }, [clampWidth]);

    const startResizing = useCallback(
        (event: ReactPointerEvent<HTMLDivElement>) => {
            event.preventDefault();

            dragState.current = {
                startX: event.clientX,
                startWidth: sidebarWidth,
            };
            setIsResizing(true);
        },
        [sidebarWidth],
    );

    const resetWidth = useCallback(() => {
        setSidebarWidth(clampWidth(defaultWidth));
    }, [clampWidth, defaultWidth]);

    return {
        sidebarWidth,
        isResizing,
        startResizing,
        resetWidth,
    };
}
