import { SharedData } from '.';
import { Category } from './category';
import Pagination from './pagination';
import { InWatchlist } from './watchlist';
export interface MoviesPageProps extends SharedData {
    movies: Pagination<App.Data.VodStreamData & InWatchlist>;
    categories: Category[];
    currentCategory: Category | null;
}

export interface MovieInformationPageProps extends SharedData {
    info: App.Http.Integrations.LionzTv.Responses.VodInformation;
    in_watchlist: boolean;
}
