import { SharedData } from '.';
import Pagination from './pagination';

export interface CategorySidebarItem {
    id: string;
    name: string;
    disabled: boolean;
    isUncategorized: boolean;
}

export type PersonalizedCategorySidebarData = App.Data.CategorySidebarData;
export type CategoryBrowseFilters = App.Data.CategoryBrowseFiltersData;

export interface SeriesPageProps extends SharedData {
    series: Pagination<App.Data.SeriesData & App.Data.InWatchlistData>;
    categories: PersonalizedCategorySidebarData;
    filters: CategoryBrowseFilters;
}

export interface SeriesInformationPageProps extends SharedData {
    info: App.Http.Integrations.LionzTv.Responses.SeriesInformation;
    in_watchlist: boolean;
    monitor: App.Data.AutoEpisodes.SeriesMonitorData | null;
    category_context: App.Data.DetailPageCategoryChipData[];
    preset_times: string[];
    backfill_preset_counts: number[];
    run_now_cooldown_seconds: number;
}

export type SeasonsWithEpisodes = Pick<
    App.Http.Integrations.LionzTv.Responses.SeriesInformation,
    'seasonsWithEpisodes'
>['seasonsWithEpisodes'];
