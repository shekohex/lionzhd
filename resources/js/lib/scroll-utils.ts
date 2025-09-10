/**
 * Scroll utility functions for smooth navigation and scroll management
 */

/**
 * Smoothly scroll to the top of the page
 * @param behavior - Scroll behavior ('smooth' or 'instant')
 */
export function scrollToTop(behavior: ScrollBehavior = 'smooth'): void {
    window.scrollTo({
        top: 0,
        left: 0,
        behavior,
    });
}

/**
 * Smoothly scroll to a specific element
 * @param element - Target element or element ID
 * @param behavior - Scroll behavior ('smooth' or 'instant')
 * @param offset - Optional offset from the top in pixels
 */
export function scrollToElement(
    element: Element | string,
    behavior: ScrollBehavior = 'smooth',
    offset: number = 0,
): void {
    const target = typeof element === 'string' ? document.getElementById(element) : element;

    if (!target) {
        console.warn('Scroll target element not found');
        return;
    }

    const elementPosition = target.getBoundingClientRect().top;
    const offsetPosition = elementPosition + window.pageYOffset - offset;

    window.scrollTo({
        top: offsetPosition,
        behavior,
    });
}

/**
 * Get current scroll position
 * @returns Object with x and y scroll positions
 */
export function getScrollPosition(): { x: number; y: number } {
    return {
        x: window.pageXOffset || document.documentElement.scrollLeft,
        y: window.pageYOffset || document.documentElement.scrollTop,
    };
}

/**
 * Check if user has scrolled near the bottom of the page
 * @param threshold - Distance from bottom in pixels (default: 200)
 * @returns True if near bottom
 */
export function isNearBottom(threshold: number = 200): boolean {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    const windowHeight = window.innerHeight;
    const documentHeight = document.documentElement.scrollHeight;

    return scrollTop + windowHeight >= documentHeight - threshold;
}

/**
 * Get scroll percentage (0-100)
 * @returns Percentage of page scrolled
 */
export function getScrollPercentage(): number {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    const documentHeight = document.documentElement.scrollHeight;
    const windowHeight = window.innerHeight;

    if (documentHeight <= windowHeight) {
        return 100; // If content fits in viewport, consider it 100% scrolled
    }

    return Math.round((scrollTop / (documentHeight - windowHeight)) * 100);
}

/**
 * Debounced scroll event handler
 * @param callback - Function to call on scroll
 * @param delay - Debounce delay in milliseconds (default: 100)
 * @returns Cleanup function
 */
export function onScroll(callback: () => void, delay: number = 100): () => void {
    let timeoutId: NodeJS.Timeout;

    const handler = () => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(callback, delay);
    };

    window.addEventListener('scroll', handler, { passive: true });

    return () => {
        clearTimeout(timeoutId);
        window.removeEventListener('scroll', handler);
    };
}

/**
 * Save current scroll position to session storage
 * @param key - Storage key
 */
export function saveScrollPosition(key: string): void {
    const position = getScrollPosition();
    try {
        sessionStorage.setItem(key, JSON.stringify(position));
    } catch (error) {
        console.warn('Failed to save scroll position:', error);
    }
}

/**
 * Restore scroll position from session storage
 * @param key - Storage key
 * @param behavior - Scroll behavior ('smooth' or 'instant')
 */
export function restoreScrollPosition(key: string, behavior: ScrollBehavior = 'instant'): void {
    try {
        const saved = sessionStorage.getItem(key);
        if (saved) {
            const position = JSON.parse(saved);
            window.scrollTo({
                top: position.y,
                left: position.x,
                behavior,
            });
            sessionStorage.removeItem(key); // Clean up after use
        }
    } catch (error) {
        console.warn('Failed to restore scroll position:', error);
    }
}
