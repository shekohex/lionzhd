import { SharedData } from '.';

export interface WelcomePageProps extends SharedData {
    featured: {
        movies: App.Data.VodStreamData[];
        series: App.Data.SeriesData[];
    };
}
