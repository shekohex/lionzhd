import { SharedData } from '.';
import { Category } from './category';
import Pagination from './pagination';
export interface SeriesPageProps extends SharedData {
    series: Pagination<App.Data.SeriesData & App.Data.InWatchlistData>;
    categories: Category[];
    currentCategory: Category | null;
}

export interface SeriesInformationPageProps extends SharedData {
    info: App.Http.Integrations.LionzTv.Responses.SeriesInformation;
    in_watchlist: boolean;
}

export type SeasonsWithEpisodes = Pick<
    App.Http.Integrations.LionzTv.Responses.SeriesInformation,
    'seasonsWithEpisodes'
>['seasonsWithEpisodes'];
