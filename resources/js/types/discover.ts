import { SharedData } from '.';
import { VodStream } from './movies';
import { Series } from './series';

export interface DiscoverPageProps extends SharedData {
    movies: VodStream[];
    series: Series[];
}
