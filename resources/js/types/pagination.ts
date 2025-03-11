// Laravel Pagination Response shape.
// https://laravel.com/docs/12.x/pagination#paginating-eloquent-results
/**
 * Interface representing a paginated response.
 *
 * @template T - The type of the data items.
 */
export default interface Pagination<T> {
    /**
     * The current page number.
     */
    current_page: number;

    /**
     * An array of data items for the current page.
     */
    data: T[];

    /**
     * The URL of the first page.
     */
    first_page_url: string;

    /**
     * The index of the first item on the current page.
     */
    from: number;

    /**
     * The last page number.
     */
    last_page: number;

    /**
     * The URL of the last page.
     */
    last_page_url: string;

    /**
     * An array of link objects for pagination navigation.
     */
    links: Link[];

    /**
     * The URL of the next page, if available.
     */
    next_page_url: string;

    /**
     * The base path for the pagination URLs.
     */
    path: string;

    /**
     * The number of items per page.
     */
    per_page: number;

    /**
     * The URL of the previous page, if available.
     */
    prev_page_url?: string;

    /**
     * The index of the last item on the current page.
     */
    to: number;

    /**
     * The total number of items across all pages.
     */
    total: number;
}

export interface Link {
    url?: string;
    label: string;
    active: boolean;
}
