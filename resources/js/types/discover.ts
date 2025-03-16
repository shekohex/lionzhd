import { SharedData } from '.';
import { VodStream } from './movies';
import { Series } from './series';
import { InWatchlist } from './watchlist';

export interface DiscoverPageProps extends SharedData {
    movies: (VodStream & InWatchlist)[];
    series: (Series & InWatchlist)[];
}
