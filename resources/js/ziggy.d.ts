/* This file is generated by Ziggy. */
declare module 'ziggy-js' {
    interface RouteList {
        telescope: [
            {
                name: 'view';
                required: false;
            },
        ];
        home: [];
        discover: [];
        'search.lightweight': [];
        'search.full': [];
        movies: [];
        'movies.show': [
            {
                name: 'model';
                required: true;
            },
        ];
        'movies.cache': [
            {
                name: 'model';
                required: true;
                binding: 'stream_id';
            },
        ];
        'movies.watchlist': [
            {
                name: 'model';
                required: true;
            },
        ];
        'movies.download': [
            {
                name: 'model';
                required: true;
            },
        ];
        series: [];
        'series.show': [
            {
                name: 'model';
                required: true;
            },
        ];
        'series.cache': [
            {
                name: 'model';
                required: true;
                binding: 'series_id';
            },
        ];
        'series.watchlist': [
            {
                name: 'model';
                required: true;
            },
        ];
        'series.download.single': [
            {
                name: 'model';
                required: true;
                binding: 'series_id';
            },
            {
                name: 'season';
                required: true;
            },
            {
                name: 'episode';
                required: true;
            },
        ];
        'series.download': [
            {
                name: 'model';
                required: true;
            },
        ];
        'series.download.batch': [
            {
                name: 'model';
                required: true;
                binding: 'series_id';
            },
        ];
        watchlist: [];
        'watchlist.store': [];
        'watchlist.destroy': [
            {
                name: 'id';
                required: true;
            },
        ];
        downloads: [];
        'downloads.edit': [
            {
                name: 'model';
                required: true;
                binding: 'id';
            },
        ];
        'downloads.destroy': [
            {
                name: 'model';
                required: true;
                binding: 'id';
            },
        ];
        'profile.edit': [];
        'profile.update': [];
        'profile.destroy': [];
        'password.edit': [];
        'password.update': [];
        'xtreamcodes.edit': [];
        'xtreamcodes.update': [];
        'aria2.edit': [];
        'aria2.update': [];
        appearance: [];
        register: [];
        login: [];
        'password.request': [];
        'password.email': [];
        'password.reset': [
            {
                name: 'token';
                required: true;
            },
        ];
        'password.store': [];
        'verification.notice': [];
        'verification.verify': [
            {
                name: 'id';
                required: true;
            },
            {
                name: 'hash';
                required: true;
            },
        ];
        'verification.send': [];
        'password.confirm': [];
        logout: [];
        'storage.local': [
            {
                name: 'path';
                required: true;
            },
        ];
    }
}
export {};
