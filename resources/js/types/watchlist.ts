import { SharedData } from '.';

export interface WatchlistItem {
    id: number;
    type: 'movie' | 'series';
    watchableId: number;
    name: string;
    cover: string;
    addedAt: string;
}

export interface WatchlistPageProps extends SharedData {
    items: WatchlistItem[];
    filter: 'all' | 'movies' | 'series';
}

export interface AddToWatchlistRequest {
    type: 'movie' | 'series';
    id: number;
}

export interface AddToWatchlistResponse extends SharedData {
    watchableType: 'movie' | 'series';
    watchableId: number;
    itemId: number;
}
