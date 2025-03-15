import { SharedData } from '.';
import { VodStream } from './movies';
import { Series } from './series';

export interface WelcomePageProps extends SharedData {
    featured: {
        movies: Pick<VodStream, 'name' | 'num' | 'rating_5based' | 'added' | 'stream_icon'>[];
        series: Pick<Series, 'name' | 'num' | 'rating_5based' | 'last_modified' | 'cover' | 'plot'>[];
    };
}
