import { SharedData } from '.';
import Pagination from './pagination';
export interface SeriesPageProps extends SharedData {
    series: Pagination<App.Data.SeriesData & App.Data.InWatchlistData>;
}

export interface SeriesInformationPageProps extends SharedData {
    info: App.Http.Integrations.LionzTv.Responses.SeriesInformation;
    in_watchlist: boolean;
}

export type SeasonsWithEpisodes = Pick<
    App.Http.Integrations.LionzTv.Responses.SeriesInformation,
    'seasonsWithEpisodes'
>['seasonsWithEpisodes'];
