import { SharedData } from '.';
import Pagination from './pagination';
import { InWatchlist } from './watchlist';

export interface CategorySidebarItem {
    id: string;
    name: string;
    disabled: boolean;
    isUncategorized: boolean;
}

export type PersonalizedCategorySidebarData = App.Data.CategorySidebarData;
export type CategoryBrowseFilters = App.Data.CategoryBrowseFiltersData;

export interface MoviesPageProps extends SharedData {
    movies: Pagination<App.Data.VodStreamData & InWatchlist>;
    categories: PersonalizedCategorySidebarData;
    filters: CategoryBrowseFilters;
}

export interface MovieInformationPageProps extends SharedData {
    info: App.Http.Integrations.LionzTv.Responses.VodInformation;
    in_watchlist: boolean;
}
