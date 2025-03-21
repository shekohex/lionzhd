import { SharedData } from '.';
import Pagination from './pagination';
export interface SeriesPageProps extends SharedData {
    series: Pagination<App.Data.SeriesData & App.Data.InWatchlistData>;
}

export interface SeriesInformationPageProps extends SharedData {
    num: number;
    series: App.Http.Integrations.LionzTv.Responses.SeriesInformation;
    in_watchlist: boolean;
}
