import { SharedData } from '.';

export interface DiscoverPageProps extends SharedData {
    movies: (App.Data.VodStreamData & App.Data.InWatchlistData)[];
    series: (App.Data.SeriesData & App.Data.InWatchlistData)[];
}
