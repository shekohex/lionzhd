declare namespace App.Data {
    export type Aria2ConfigData = {
        host: string;
        port: number;
        secret: string;
        use_ssl: boolean;
    };
    export type DiscoverMediaData = {
        movies: Array<App.Data.VodStreamData | App.Data.InWatchlistData>;
        series: Array<App.Data.SeriesData | App.Data.InWatchlistData>;
    };
    export type FeaturedMediaData = {
        movies: Array<App.Data.VodStreamData>;
        series: Array<App.Data.SeriesData>;
    };
    export type InWatchlistData = {
        in_watchlist: boolean;
    };
    export type LightweightSearchData = {
        movies: Array<App.Data.VodStreamData>;
        series: Array<App.Data.SeriesData>;
        filters: App.Data.LightweightSearchFiltersData;
    };
    export type LightweightSearchFiltersData = {
        q?: string;
        per_page: number;
    };
    export type SearchMediaData = {
        q?: string;
        page?: number;
        per_page?: number;
        media_type?: App.Enums.MediaType;
        sort_by?: App.Enums.SearchSortby;
    };
    export type SeriesData = {
        num: number;
        name: string;
        series_id: number;
        cover: string;
        plot: string;
        cast: string;
        director: string;
        genre: string;
        backdrop_path: Array<string>;
        releaseDate: string;
        last_modified: string;
        rating: number;
        rating_5based: number;
        created_at: string;
        updated_at: string;
    };
    export type VodStreamData = {
        num: number;
        name: string;
        stream_type: string;
        stream_id: number;
        stream_icon: string;
        rating: string;
        rating_5based: number;
        added: string;
        is_adult: boolean;
        category_id?: number;
        container_extension: string;
        custom_sid?: string;
        direct_source?: string;
        created_at: string;
        updated_at: string;
    };
    export type XtreamCodesConfigData = {
        host: string;
        port: number;
        username: string;
        password: string;
    };
}
declare namespace App.Enums {
    export type MediaType = 'movie' | 'series';
    export type SearchSortby = 'popular' | 'latest' | 'rating';
}
declare namespace App.Http.Integrations.LionzTv.Responses {
    export type AudioMetadata = {
        index: number;
        codecName: string;
        codecLongName: string;
        profile: string;
        codecType: string;
        codecTimeBase: string;
        codecTagString: string;
        codecTag: string;
        sampleFmt: string;
        sampleRate: string;
        channels: number;
        channelLayout: string;
        bitsPerSample: number;
        rFrameRate: string;
        avgFrameRate: string;
        timeBase: string;
        startPts: number;
        startTime: string;
        durationTs: number;
        duration: string;
        bitRate: string;
        maxBitRate: string;
        nbFrames: string;
        disposition: App.Http.Integrations.LionzTv.Responses.Disposition;
        tags: { language: string; DURATION: string };
    };
    export type Disposition = {
        default: number;
        dub: number;
        original: number;
        comment: number;
        lyrics: number;
        karaoke: number;
        forced: number;
        hearingImpaired: number;
        visualImpaired: number;
        cleanEffects: number;
        attachedPic: number;
        timedThumbnails: number;
    };
    export type Episode = {
        id: string;
        episodeNum: number;
        title: string;
        containerExtension: string;
        durationSecs: number;
        duration: string;
        video?: App.Http.Integrations.LionzTv.Responses.VideoMetadata;
        audio?: App.Http.Integrations.LionzTv.Responses.AudioMetadata;
        bitrate: number;
        customSid: string;
        added: string;
        season: number;
        directSource: string;
    };
    export type Movie = {
        streamId: number;
        name: string;
        added: string;
        categoryId: string;
        containerExtension: string;
        customSid: string;
        directSource: string;
    };
    export type SeriesInformation = {
        seriesId: number;
        seasons: Array<string>;
        name: string;
        cover: string;
        plot: string;
        cast: string;
        director: string;
        genre: string;
        releaseDate: string;
        lastModified: string;
        rating: string;
        rating_5based: number;
        backdropPath: Array<string>;
        youtubeTrailer: string;
        episodeRunTime: string;
        categoryId: string;
        seasonsWithEpisodes: Record<string, Array<App.Http.Integrations.LionzTv.Responses.Episode>>;
    };
    export type VideoMetadata = {
        index: number;
        codecName: string;
        codecLongName: string;
        profile: string;
        codecType: string;
        codecTimeBase: string;
        codecTagString: string;
        codecTag: string;
        width: number;
        height: number;
        codedWidth: number;
        codedHeight: number;
        hasBFrames: number;
        pixFmt: string;
        level: number;
        chromaLocation: string;
        refs: number;
        isAvc: string;
        nalLengthSize: string;
        rFrameRate: string;
        avgFrameRate: string;
        timeBase: string;
        startPts: number;
        startTime: string;
        durationTs: number;
        duration: string;
        bitRate: string;
        bitsPerRawSample: string;
        nbFrames: string;
        disposition: App.Http.Integrations.LionzTv.Responses.Disposition;
        tags: { HANDLER_NAME: string; DURATION: string };
    };
    export type VodInformation = {
        vodId: number;
        movieImage: string;
        tmdbId: string;
        backdrop: string;
        youtubeTrailer: string;
        genre: string;
        plot: string;
        cast: string;
        rating: string;
        director: string;
        releaseDate: string;
        backdropPath: Array<string>;
        durationSecs: number;
        duration: string;
        video: App.Http.Integrations.LionzTv.Responses.VideoMetadata;
        audio: App.Http.Integrations.LionzTv.Responses.AudioMetadata;
        bitrate: number;
        movie: App.Http.Integrations.LionzTv.Responses.Movie;
    };
}
