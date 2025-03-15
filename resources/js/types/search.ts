import { ArrayType, PrimitiveType, SharedData } from '.';
import { VodStream } from './movies';
import Pagination from './pagination';
import { Series } from './series';

export type MediaType = 'movie' | 'series';
export type SortBy = 'popular' | 'rating' | 'latest';

export interface LightweightMedia<K extends MediaType = MediaType> {
    num: number;
    name: string;
    type: K;
    poster?: string;
}

export interface SearchRequest {
    q?: string;
    per_page?: number;
    media_type?: MediaType;
    sort_by?: SortBy;
    [key: string]: PrimitiveType | ArrayType;
}

export type SearchKind = 'full' | 'lightweight';

export interface SearchResult<K extends SearchKind> extends SharedData {
    movies: K extends 'full' ? Pagination<VodStream> : Pagination<LightweightMedia<'movie'>>;
    series: K extends 'full' ? Pagination<Series> : Pagination<LightweightMedia<'series'>>;
    filters: {
        q: string;
        per_page: number;
        media_type: MediaType;
        sort_by: SortBy;
    };
}
