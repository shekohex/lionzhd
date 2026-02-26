import { SharedData } from '.';
import Pagination from './pagination';
import { InWatchlist } from './watchlist';

export interface CategorySidebarItem {
    id: string;
    name: string;
    disabled: boolean;
    isUncategorized: boolean;
}

export interface CategoryBrowseFilters {
    category: string | null;
}

export interface MoviesPageProps extends SharedData {
    movies: Pagination<App.Data.VodStreamData & InWatchlist>;
    categories: CategorySidebarItem[];
    filters: CategoryBrowseFilters;
}

export interface MovieInformationPageProps extends SharedData {
    info: App.Http.Integrations.LionzTv.Responses.VodInformation;
    in_watchlist: boolean;
}
