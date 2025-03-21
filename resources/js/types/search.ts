import { SharedData } from '.';
import Pagination from './pagination';

export interface LightweightSearchResult extends SharedData {
    movies: Pagination<App.Data.VodStreamData>;
    series: Pagination<App.Data.SeriesData>;
    filters: App.Data.LightweightSearchFiltersData;
}

export interface FullSearchResult extends SharedData {
    movies: Pagination<App.Data.VodStreamData>;
    series: Pagination<App.Data.SeriesData>;
    filters: App.Data.SearchMediaData;
}
