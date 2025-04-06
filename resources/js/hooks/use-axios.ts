import { usePage } from '@inertiajs/react';
import axios, { AxiosError, AxiosRequestConfig, AxiosResponse } from 'axios';
import { useCallback, useEffect, useRef, useState } from 'react';

// Axios instance with CSRF protection for Laravel
const axiosInstance = axios.create({
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/json',
        'X-Inertia': true,
        Accept: 'text/html, application/xhtml+xml',
    },
    // This ensures cookies are sent with requests
    withCredentials: true,
    // This ensures the XSRF-TOKEN header is set
    withXSRFToken: true,
});

interface UseAxiosState<T> {
    data: T | null;
    loading: boolean;
    error: Error | AxiosError | null;
    response: AxiosResponse | null;
}

interface UseAxiosResponse<T> extends UseAxiosState<T> {
    execute: (config?: AxiosRequestConfig) => Promise<void>;
}

/**
 * A custom React hook for making Axios HTTP requests with built-in state management.
 *
 * This hook wraps the axios instance and provides a convenient way to handle API requests
 * in React components with loading state, error handling, and response management.
 *
 * @template T - The type of data expected in the response
 * @param {AxiosRequestConfig} config - The axios request configuration
 * @param {boolean} [executeImmediately=true] - Whether to execute the request immediately when the component mounts
 * @returns {UseAxiosResponse<T>} An object containing the request state and execute function
 */
function useAxios<T>(config: AxiosRequestConfig, executeImmediately = true): UseAxiosResponse<T> {
    const currentPage = usePage();
    axiosInstance.interceptors.request.use((request) => {
        if (currentPage.version) {
            request.headers['X-Inertia-Version'] = currentPage.version;
        }
        return request;
    });
    const [state, setState] = useState<UseAxiosState<T>>({
        data: null,
        loading: executeImmediately,
        error: null,
        response: null,
    });

    const configRef = useRef(config);

    // Store the latest config in a ref
    useEffect(() => {
        configRef.current = config;
    }, [config]);

    const execute = useCallback(async (newConfig?: AxiosRequestConfig): Promise<void> => {
        const currentConfig = { ...configRef.current, ...newConfig };

        setState((prevState) => ({
            ...prevState,
            loading: true,
            error: null,
        }));

        try {
            const response = await axiosInstance.request<T>(currentConfig);
            setState({
                data: response.data,
                loading: false,
                error: null,
                response,
            });
        } catch (error) {
            setState({
                data: null,
                loading: false,
                error: error as Error | AxiosError,
                response: null,
            });
        }
    }, []);

    useEffect(() => {
        if (executeImmediately) {
            execute();
        }
    }, [executeImmediately, execute]);

    return { ...state, execute };
}

export default useAxios;
