declare namespace App {
    namespace Data {
        export type Aria2ConfigData = {
            host: string;
            port: number;
            secret: string;
            use_ssl: boolean;
        };
        export type BatchDownloadEpisodesData = {
            selectedEpisodes: App.Data.SelectedEpisodeData[];
        };
        export type CategoryBrowseFiltersData = {
            category: string | null;
            recovery: App.Data.CategoryBrowseRecoveryStateData | null;
        };
        export type CategoryBrowseRecoveryStateData = {
            allCategoriesEmptyDueToIgnored: boolean;
            allCategoriesEmptyDueToHidden: boolean;
        };
        export type CategorySidebarData = {
            visibleItems: App.Data.CategorySidebarItemData[];
            hiddenItems: App.Data.CategorySidebarItemData[];
            selectedCategoryIsHidden: boolean;
            selectedCategoryName: string | null;
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
            pinRank: number | null;
            sortOrder: number | null;
            isUncategorized: boolean;
            isIgnored: boolean;
        };
        export type DetailPageCategoryChipData = {
            id: string;
            name: string;
            href: string;
        };
        export type DiscoverMediaData = {
            movies: (App.Data.VodStreamData | App.Data.InWatchlistData)[];
            series: (App.Data.SeriesData | App.Data.InWatchlistData)[];
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
            readonly action: App.Enums.MediaDownloadAction;
            readonly delete_partial: boolean;
            readonly restart_from_zero: boolean;
        };
        export type FeaturedMediaData = {
            movies: App.Data.VodStreamData[];
            series: App.Data.SeriesData[];
        };
        export type InWatchlistData = {
            in_watchlist: boolean;
        };
        export type LightweightSearchData = {
            movies: Illuminate.LengthAwarePaginator<App.Data.VodStreamData>;
            series: Illuminate.LengthAwarePaginator<App.Data.SeriesData>;
            filters: App.Data.LightweightSearchFiltersData;
        };
        export type LightweightSearchFiltersData = {
            q: string | null;
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
            user_id: number | null;
            desired_paused: boolean;
            canceled_at: string | null;
            cancel_delete_partial: boolean;
            last_error_code: number | null;
            last_error_message: string | null;
            retry_attempt: number;
            retry_next_at: string | null;
            download_files: string[];
            created_at: string;
            updated_at: string;
            media: App.Data.VodStreamData | App.Data.SeriesData;
            owner: App.Data.MediaDownloadOwnerData | null;
            downloadStatus: App.Data.MediaDownloadStatusData | null;
            season: number | null;
            episode: number | null;
        };
        export type MediaDownloadStatusData = {
            gid: string;
            status: App.Enums.MediaDownloadStatus;
            totalLength: number;
            completedLength: number;
            downloadSpeed: number;
            errorCode: number;
            errorMessage: string | null;
            dir: string;
            files: App.Data.DownloadedFileData[];
        };
        export type SearchMediaData = {
            q: string | null;
            per_page: number;
            page: number | null;
            media_type: App.Enums.MediaType | null;
            sort_by: App.Enums.SearchSortby | null;
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
            backdrop_path: string[];
            releaseDate: string;
            last_modified: string;
            category_id: string | null;
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
            category_id: string | null;
            container_extension: string;
            custom_sid: string | null;
            direct_source: string | null;
            created_at: string;
            updated_at: string;
        };
        export type XtreamCodesConfigData = {
            host: string;
            port: number;
            username: string;
            password: string;
        };
        namespace AutoEpisodes {
            export type MonitoringPageData = {
                can_manage_schedules: boolean;
                is_paused: boolean;
                auto_episodes_paused_at: string | null;
                monitors: App.Data.AutoEpisodes.SeriesMonitorData[];
                events: App.Data.AutoEpisodes.SeriesMonitorEventData[];
                preset_times: string[];
                backfill_preset_counts: number[];
                run_now_cooldown_seconds: number;
            };
            export type SeriesMonitorData = {
                id: number;
                series_id: number;
                series_name: string | null;
                series_cover: string | null;
                enabled: boolean;
                timezone: string;
                schedule_type: App.Enums.AutoEpisodes.MonitorScheduleType;
                schedule_daily_time: string | null;
                schedule_weekly_days: number[];
                schedule_weekly_time: string | null;
                monitored_seasons: number[];
                per_run_cap: number;
                next_run_at: string | null;
                last_attempt_at: string | null;
                last_attempt_status: App.Enums.AutoEpisodes.SeriesMonitorRunStatus | null;
                last_successful_check_at: string | null;
                run_now_available_at: string | null;
            };
            export type SeriesMonitorEventData = {
                id: number;
                monitor_id: number;
                series_id: number | null;
                series_name: string | null;
                series_cover: string | null;
                type: App.Enums.AutoEpisodes.SeriesMonitorEventType;
                reason: string | null;
                episode_id: string | null;
                season: number | null;
                episode_num: number | null;
                created_at: string | null;
            };
        }
    }
    namespace Enums {
        export type CategorySyncRunStatus = 'running' | 'success' | 'success_with_warnings' | 'failed';
        export type MediaDownloadAction = 'pause' | 'resume' | 'cancel' | 'remove' | 'retry';
        export type MediaDownloadStatus =
            | 'unknown'
            | 'active'
            | 'waiting'
            | 'paused'
            | 'error'
            | 'complete'
            | 'removed';
        export type MediaType = 'movie' | 'series';
        export type SearchSortby = 'popular' | 'latest' | 'rating';
        export type UserRole = 'admin' | 'member';
        export type UserSubtype = 'internal' | 'external';
        namespace AutoEpisodes {
            export type MonitorScheduleType = 'hourly' | 'daily' | 'weekly';
            export type SeriesMonitorEventType = 'queued' | 'duplicate' | 'deferred' | 'skipped' | 'error';
            export type SeriesMonitorRunStatus = 'running' | 'success' | 'failed' | 'success_with_warnings';
            export type SeriesMonitorRunTrigger = 'scheduled' | 'manual' | 'backfill';
        }
    }
    namespace Http {
        namespace Integrations {
            namespace LionzTv {
                namespace Responses {
                    export type AudioMetadata = {
                        readonly index: number;
                        readonly codecName: string;
                        readonly codecLongName: string;
                        readonly profile: string;
                        readonly codecType: string;
                        readonly codecTimeBase: string;
                        readonly codecTagString: string;
                        readonly codecTag: string;
                        readonly sampleFmt: string;
                        readonly sampleRate: string;
                        readonly channels: number;
                        readonly channelLayout: string;
                        readonly bitsPerSample: number;
                        readonly rFrameRate: string;
                        readonly avgFrameRate: string;
                        readonly timeBase: string;
                        readonly startPts: number;
                        readonly startTime: string;
                        readonly durationTs: number;
                        readonly duration: string;
                        readonly bitRate: string;
                        readonly maxBitRate: string;
                        readonly nbFrames: string;
                        readonly disposition: App.Http.Integrations.LionzTv.Responses.Disposition;
                        readonly tags: {
                            language: string;
                            DURATION: string;
                        };
                    };
                    export type Disposition = {
                        readonly default: number;
                        readonly dub: number;
                        readonly original: number;
                        readonly comment: number;
                        readonly lyrics: number;
                        readonly karaoke: number;
                        readonly forced: number;
                        readonly hearingImpaired: number;
                        readonly visualImpaired: number;
                        readonly cleanEffects: number;
                        readonly attachedPic: number;
                        readonly timedThumbnails: number;
                    };
                    export type Episode = {
                        readonly id: string;
                        readonly episodeNum: number;
                        readonly title: string;
                        readonly containerExtension: string;
                        readonly durationSecs: number;
                        readonly duration: string;
                        readonly video: App.Http.Integrations.LionzTv.Responses.VideoMetadata | null;
                        readonly audio: App.Http.Integrations.LionzTv.Responses.AudioMetadata | null;
                        readonly bitrate: number;
                        readonly customSid: string;
                        readonly added: string;
                        readonly season: number;
                        readonly directSource: string;
                    };
                    export type Movie = {
                        readonly streamId: number;
                        readonly name: string;
                        readonly added: string;
                        readonly categoryId: string;
                        readonly containerExtension: string;
                        readonly customSid: string;
                        readonly directSource: string;
                    };
                    export type SeriesInformation = {
                        readonly seriesId: number;
                        readonly seasons: string[];
                        readonly name: string;
                        readonly cover: string;
                        readonly plot: string;
                        readonly cast: string;
                        readonly director: string;
                        readonly genre: string;
                        readonly releaseDate: string;
                        readonly lastModified: string;
                        readonly rating: string;
                        readonly rating_5based: number;
                        readonly backdropPath: string[];
                        readonly youtubeTrailer: string;
                        readonly episodeRunTime: string;
                        readonly categoryId: string;
                        readonly seasonsWithEpisodes: Record<string, App.Http.Integrations.LionzTv.Responses.Episode[]>;
                    };
                    export type VideoMetadata = {
                        readonly index: number;
                        readonly codecName: string;
                        readonly codecLongName: string;
                        readonly profile: string;
                        readonly codecType: string;
                        readonly codecTimeBase: string;
                        readonly codecTagString: string;
                        readonly codecTag: string;
                        readonly width: number;
                        readonly height: number;
                        readonly codedWidth: number;
                        readonly codedHeight: number;
                        readonly hasBFrames: number;
                        readonly pixFmt: string;
                        readonly level: number;
                        readonly chromaLocation: string;
                        readonly refs: number;
                        readonly isAvc: string;
                        readonly nalLengthSize: string;
                        readonly rFrameRate: string;
                        readonly avgFrameRate: string;
                        readonly timeBase: string;
                        readonly startPts: number;
                        readonly startTime: string;
                        readonly durationTs: number;
                        readonly duration: string;
                        readonly bitRate: string;
                        readonly bitsPerRawSample: string;
                        readonly nbFrames: string;
                        readonly disposition: App.Http.Integrations.LionzTv.Responses.Disposition;
                        readonly tags: {
                            HANDLER_NAME: string;
                            DURATION: string;
                        };
                    };
                    export type VodInformation = {
                        readonly vodId: number;
                        readonly movieImage: string;
                        readonly tmdbId: string;
                        readonly backdrop: string;
                        readonly youtubeTrailer: string;
                        readonly genre: string;
                        readonly plot: string;
                        readonly cast: string;
                        readonly rating: string;
                        readonly director: string;
                        readonly releaseDate: string;
                        readonly backdropPath: string[];
                        readonly durationSecs: number;
                        readonly duration: string;
                        readonly video: App.Http.Integrations.LionzTv.Responses.VideoMetadata;
                        readonly audio: App.Http.Integrations.LionzTv.Responses.AudioMetadata;
                        readonly bitrate: number;
                        readonly movie: App.Http.Integrations.LionzTv.Responses.Movie;
                    };
                }
            }
        }
    }
}
declare namespace Illuminate {
    export type CursorPaginator<TKey, TValue> = {
        data: TKey extends string ? Record<TKey, TValue> : TValue[];
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
        meta: {
            path: string;
            per_page: number;
            next_cursor: string | null;
            next_page_url: string | null;
            prev_cursor: string | null;
            prev_page_url: string | null;
        };
    };
    export type CursorPaginatorInterface<TKey, TValue> = Illuminate.CursorPaginator<TKey, TValue>;
    export type LengthAwarePaginator<TKey, TValue> = {
        data: TKey extends string ? Record<TKey, TValue> : TValue[];
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
        meta: {
            total: number;
            current_page: number;
            first_page_url: string;
            from: number | null;
            last_page: number;
            last_page_url: string;
            next_page_url: string | null;
            path: string;
            per_page: number;
            prev_page_url: string | null;
            to: number | null;
        };
    };
    export type LengthAwarePaginatorInterface<TKey, TValue> = Illuminate.LengthAwarePaginator<TKey, TValue>;
}
declare namespace Spatie {
    namespace LaravelData {
        export type CursorPaginatedDataCollection<TKey, TValue> = Illuminate.CursorPaginator<TKey, TValue>;
        export type PaginatedDataCollection<TKey, TValue> = Illuminate.LengthAwarePaginator<TKey, TValue>;
    }
}
