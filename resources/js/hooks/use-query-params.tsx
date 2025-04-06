import { ArrayType, PrimitiveType } from '@/types';
import { useCallback, useEffect, useMemo, useState } from 'react';

/**
 * Type definitions for supported query parameter value types
 */
type QueryParamValue = PrimitiveType | ArrayType;

/**
 * Type for the query parameters object
 */
export type QueryParams<T> = {
    [K in keyof T]: QueryParamValue;
};

/**
 * Return type for the useQueryParams hook
 */
export type UseQueryParamsReturn<T> = [
    Partial<T>, // Current parameters
    (newParams: Partial<T> | ((prev: Partial<T>) => Partial<T>)) => void, // Update function
    {
        reset: () => void; // Reset to defaults
        remove: (...keys: (keyof T)[]) => void; // Remove specific params
    },
];

/**
 * Configuration options for the useQueryParams hook
 */
interface UseQueryParamsOptions {
    /**
     * When true, updates the URL without adding a new history entry
     * @default false
     */
    replace?: boolean;

    /**
     * When false, disables automatic URL updates when parameters change
     * @default true
     */
    updateBrowserHistory?: boolean;

    /**
     * When true, serializes empty arrays (by default they're omitted)
     * @default false
     */
    serializeEmptyArrays?: boolean;

    /**
     * Custom parameter serialization for specific keys
     */
    serializers?: {
        [key: string]: (value: QueryParamValue) => string | string[];
    };

    /**
     * Custom parameter parsing for specific keys
     */
    parsers?: {
        [key: string]: (value: string) => QueryParamValue;
    };
}

// Default configuration
const defaultOptions: UseQueryParamsOptions = {
    replace: false,
    updateBrowserHistory: true,
    serializeEmptyArrays: false,
};

/**
 * Safely determines if a value can be converted to a number
 */
const isNumeric = (value: string): boolean => {
    // Avoid converting empty string to 0
    if (value === '') return false;
    return !isNaN(Number(value)) && !isNaN(parseFloat(value));
};

/**
 * Converts URL search parameters to typed object
 */
function parseQueryParams<T extends Record<string, QueryParamValue>>(
    searchParams: URLSearchParams,
    options?: Pick<UseQueryParamsOptions, 'parsers'>,
): Partial<T> {
    const params: Record<string, QueryParamValue> = {};
    const parsers = options?.parsers || {};

    // Group parameters by removing [] suffix
    const paramGroups: Record<string, string[]> = {};

    // First pass: collect array parameters
    for (const [key, value] of searchParams.entries()) {
        if (!key) continue; // Skip empty keys

        // Handle array notation (key[])
        if (key.endsWith('[]')) {
            const baseKey = key.slice(0, -2);
            if (!paramGroups[baseKey]) {
                paramGroups[baseKey] = [];
            }
            paramGroups[baseKey].push(value);
        }
    }

    // Second pass: process all parameters
    for (const [key, value] of searchParams.entries()) {
        // Skip empty keys or array keys (already processed)
        if (!key || key.endsWith('[]')) continue;

        // Use custom parser if available
        if (parsers[key]) {
            params[key] = parsers[key](value);
            continue;
        }

        // Check if this key has array values
        if (paramGroups[key]) {
            // Fix the type issue by explicitly typing as mixed array
            params[key] = paramGroups[key].map((item) => {
                // Convert array items to appropriate types
                if (item === 'true') return true;
                if (item === 'false') return false;
                if (isNumeric(item)) return Number(item);
                return item;
            }) as (string | number | boolean)[];
            continue;
        }

        // Handle type conversion for singular values
        if (value === 'true') {
            params[key] = true;
        } else if (value === 'false') {
            params[key] = false;
        } else if (value === '') {
            params[key] = '';
        } else if (value === null || value === undefined) {
            params[key] = null;
        } else if (isNumeric(value)) {
            params[key] = Number(value);
        } else {
            params[key] = value;
        }
    }

    return params as Partial<T>;
}

/**
 * Converts typed object to URLSearchParams
 */
function stringifyQueryParams<T extends Record<string, QueryParamValue>>(
    params: Partial<T>,
    options?: Pick<UseQueryParamsOptions, 'serializeEmptyArrays' | 'serializers'>,
): URLSearchParams {
    const searchParams = new URLSearchParams();
    const serializers = options?.serializers || {};
    const serializeEmptyArrays = options?.serializeEmptyArrays || false;

    Object.entries(params).forEach(([key, value]) => {
        // Skip null or undefined values
        if (value === null || value === undefined) return;

        // Use custom serializer if available
        if (serializers[key]) {
            const serialized = serializers[key](value);
            if (Array.isArray(serialized)) {
                serialized.forEach((item) => {
                    searchParams.append(`${key}[]`, item);
                });
            } else {
                searchParams.set(key, serialized);
            }
            return;
        }

        if (Array.isArray(value)) {
            // Skip empty arrays unless configured to include them
            if (value.length === 0 && !serializeEmptyArrays) return;

            // Handle arrays by adding multiple entries with the same key
            value.forEach((item) => {
                if (item !== null && item !== undefined) {
                    searchParams.append(`${key}[]`, String(item));
                }
            });
        } else if (typeof value === 'boolean' || typeof value === 'number') {
            searchParams.set(key, String(value));
        } else {
            searchParams.set(key, value);
        }
    });

    return searchParams;
}

/**
 * Custom hook for managing URL query parameters with type safety
 *
 * @param defaultParams - Default parameter values
 * @param options - Configuration options
 * @returns Tuple containing [currentParams, updateFn, {utils}]
 *
 * @example
 * // Define the shape of your query parameters
 * type SearchParams = {
 *   q: string;
 *   page: number;
 *   tags: string[];
 *   active: boolean;
 * };
 *
 * // Use the hook with your type
 * const [params, setParams, { reset, remove }] = useQueryParams<SearchParams>({
 *   page: 1,
 *   tags: [],
 *   active: true
 * });
 *
 * // Access typed parameters
 * console.log(params.q); // string | undefined
 * console.log(params.page); // number | undefined
 *
 * // Update parameters
 * setParams({ page: params.page + 1 });
 *
 * // Function-based updates
 * setParams(prev => ({ page: (prev.page || 1) + 1 }));
 *
 * // Remove parameters
 * remove('q', 'tags');
 *
 * // Reset to defaults
 * reset();
 */
export function useQueryParams<T extends Record<string, QueryParamValue>>(
    defaultParams: Partial<T> = {},
    options: UseQueryParamsOptions = {},
): UseQueryParamsReturn<T> {
    // Merge provided options with defaults
    const mergedOptions = { ...defaultOptions, ...options };
    const { replace, updateBrowserHistory } = mergedOptions;

    // Initialize state from URL or defaults
    const [queryParams, setInternalQueryParams] = useState<Partial<T>>(() => {
        if (typeof window === 'undefined') return defaultParams;

        try {
            const searchParams = new URLSearchParams(window.location.search);
            const initialParams = parseQueryParams<T>(searchParams, {
                parsers: options.parsers,
            });

            // Merge URL params with defaults (URL params take precedence)
            return { ...defaultParams, ...initialParams };
        } catch (error) {
            console.error('Error parsing query parameters:', error);
            return defaultParams;
        }
    });

    // Memoized stringified parameters to avoid unnecessary URL updates
    const stringifiedParams = useMemo(() => {
        return stringifyQueryParams(queryParams, {
            serializeEmptyArrays: options.serializeEmptyArrays,
            serializers: options.serializers,
        }).toString();
    }, [queryParams, options.serializeEmptyArrays, options.serializers]);

    // Update URL when parameters change
    useEffect(() => {
        if (typeof window === 'undefined' || !updateBrowserHistory) return;

        try {
            const currentSearch = window.location.search.replace(/^\?/, '');

            // Only update if the URL actually changed
            if (currentSearch !== stringifiedParams) {
                const newUrl = `${window.location.pathname}${stringifiedParams ? `?${stringifiedParams}` : ''}`;

                if (replace) {
                    window.history.replaceState(null, '', newUrl);
                } else {
                    window.history.pushState(null, '', newUrl);
                }
            }
        } catch (error) {
            console.error('Error updating URL with query parameters:', error);
        }
    }, [stringifiedParams, replace, updateBrowserHistory]);

    // Listen for browser navigation events (back/forward)
    useEffect(() => {
        if (typeof window === 'undefined') return;

        const handlePopState = () => {
            try {
                const searchParams = new URLSearchParams(window.location.search);
                const newParams = parseQueryParams<T>(searchParams, {
                    parsers: options.parsers,
                });
                setInternalQueryParams((current) => ({ ...current, ...newParams }));
            } catch (error) {
                console.error('Error handling popstate event:', error);
            }
        };

        window.addEventListener('popstate', handlePopState);
        return () => {
            window.removeEventListener('popstate', handlePopState);
        };
    }, [options.parsers]);

    // Set query parameters with type safety
    const setQueryParams = useCallback((newParams: Partial<T> | ((prev: Partial<T>) => Partial<T>)) => {
        setInternalQueryParams((prevParams) => {
            try {
                // Handle function updates
                if (typeof newParams === 'function') {
                    return { ...prevParams, ...newParams(prevParams) };
                }

                // Handle object updates
                return { ...prevParams, ...newParams };
            } catch (error) {
                console.error('Error updating query parameters:', error);
                return prevParams;
            }
        });
    }, []);

    // Reset query parameters to defaults
    const resetQueryParams = useCallback(() => {
        setInternalQueryParams(defaultParams);
    }, [defaultParams]);

    // Remove specific parameters
    const removeQueryParams = useCallback((...keys: (keyof T)[]) => {
        setInternalQueryParams((prevParams) => {
            const newParams = { ...prevParams };
            keys.forEach((key) => {
                delete newParams[key];
            });
            return newParams;
        });
    }, []);

    // Return as a tuple with utility methods in an object
    return [
        queryParams,
        setQueryParams,
        {
            reset: resetQueryParams,
            remove: removeQueryParams,
        },
    ];
}
