declare namespace App.Data {
    export type Aria2ConfigData = {
        host: string;
        port: number;
        secret: string;
        use_ssl: boolean;
    };
    export type BatchDownloadEpisodesData = {
        selectedEpisodes: Array<App.Data.SelectedEpisodeData>;
    };
    export type CategoryBrowseFiltersData = {
        category?: string;
        recovery?: App.Data.CategoryBrowseRecoveryStateData;
    };
    export type CategoryBrowseRecoveryStateData = {
        allCategoriesEmptyDueToIgnored: boolean;
        allCategoriesEmptyDueToHidden: boolean;
    };
    export type CategorySidebarData = {
        visibleItems: Array<App.Data.CategorySidebarItemData>;
        hiddenItems: Array<App.Data.CategorySidebarItemData>;
        selectedCategoryIsHidden: boolean;
        selectedCategoryName?: string;
        pinLimit: number;
        canReset: boolean;
        selectedCategoryIsIgnored: boolean;
    };
    export type CategorySidebarItemData = {
        id: string;
        name: string;
        disabled: boolean;
        canNavigate: boolean;
        canEdit: boolean;
        isPinned: boolean;
        isHidden: boolean;
        pinRank?: number;
        sortOrder?: number;
        isUncategorized: boolean;
        isIgnored: boolean;
    };
    export type DetailPageCategoryChipData = {
        id: string;
        name: string;
        href: string;
    };
    export type DiscoverMediaData = {
        movies: Array<App.Data.VodStreamData | App.Data.InWatchlistData>;
        series: Array<App.Data.SeriesData | App.Data.InWatchlistData>;
    };
    export type DownloadedFileData = {
        index: number;
        path: string;
        length: number;
        completedLength: number;
        selected: boolean;
        uris: { status: string; uri: string }[];
    };
    export type EditMediaDownloadData = {
        action: App.Enums.MediaDownloadAction;
        delete_partial: boolean;
        restart_from_zero: boolean;
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
    export type MediaDownloadOwnerData = {
        id: number;
        name: string;
        email: string;
    };
    export type MediaDownloadRefData = {
        id: number;
        gid: string;
        media_id: number;
        media_type: 'movie' | 'series';
        downloadable_id: number;
        user_id?: number;
        desired_paused: boolean;
        canceled_at?: string;
        cancel_delete_partial: boolean;
        last_error_code?: number;
        last_error_message?: string;
        retry_attempt: number;
        retry_next_at?: string;
        download_files?: string[];
        created_at: string;
        updated_at: string;
        media: App.Data.VodStreamData | App.Data.SeriesData;
        owner?: App.Data.MediaDownloadOwnerData;
        downloadStatus?: App.Data.MediaDownloadStatusData;
        season?: number;
        episode?: number;
    };
    export type MediaDownloadStatusData = {
        gid: string;
        status: App.Enums.MediaDownloadStatus;
        totalLength: number;
        completedLength: number;
        downloadSpeed: number;
        errorCode: number;
        errorMessage?: string;
        dir: string;
        files: { [key: number]: App.Data.DownloadedFileData };
    };
    export type SearchMediaData = {
        q?: string;
        per_page: number;
        page?: number;
        media_type?: App.Enums.MediaType;
        sort_by?: App.Enums.SearchSortby;
    };
    export type SelectedEpisodeData = {
        season: number;
        episodeNum: number;
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
        category_id?: string;
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
        category_id?: string;
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
declare namespace App.Data.AutoEpisodes {
    export type MonitoringPageData = {
        can_manage_schedules: boolean;
        is_paused: boolean;
        auto_episodes_paused_at?: string;
        monitors: App.Data.AutoEpisodes.SeriesMonitorData[];
        events: App.Data.AutoEpisodes.SeriesMonitorEventData[];
        preset_times: string[];
        backfill_preset_counts: number[];
        run_now_cooldown_seconds: number;
    };
    export type SeriesMonitorData = {
        id: number;
        series_id: number;
        series_name?: string;
        series_cover?: string;
        enabled: boolean;
        timezone: string;
        schedule_type: App.Enums.AutoEpisodes.MonitorScheduleType;
        schedule_daily_time?: string;
        schedule_weekly_days: number[];
        schedule_weekly_time?: string;
        monitored_seasons: number[];
        per_run_cap: number;
        next_run_at?: string;
        last_attempt_at?: string;
        last_attempt_status?: App.Enums.AutoEpisodes.SeriesMonitorRunStatus;
        last_successful_check_at?: string;
        run_now_available_at?: string;
    };
    export type SeriesMonitorEventData = {
        id: number;
        monitor_id: number;
        series_id?: number;
        series_name?: string;
        series_cover?: string;
        type: App.Enums.AutoEpisodes.SeriesMonitorEventType;
        reason?: string;
        episode_id?: string;
        season?: number;
        episode_num?: number;
        created_at?: string;
    };
}
declare namespace App.Enums {
    export type CategorySyncRunStatus = 'running' | 'success' | 'success_with_warnings' | 'failed';
    export type MediaDownloadAction = 'pause' | 'resume' | 'cancel' | 'remove' | 'retry';
    export type MediaDownloadStatus = 'unknown' | 'active' | 'waiting' | 'paused' | 'error' | 'complete' | 'removed';
    export type MediaType = 'movie' | 'series';
    export type SearchSortby = 'popular' | 'latest' | 'rating';
    export type UserRole = 'admin' | 'member';
    export type UserSubtype = 'internal' | 'external';
}
declare namespace App.Enums.AutoEpisodes {
    export type MonitorScheduleType = 'hourly' | 'daily' | 'weekly';
    export type SeriesMonitorEventType = 'queued' | 'duplicate' | 'deferred' | 'skipped' | 'error';
    export type SeriesMonitorRunStatus = 'running' | 'success' | 'failed' | 'success_with_warnings';
    export type SeriesMonitorRunTrigger = 'scheduled' | 'manual' | 'backfill';
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
