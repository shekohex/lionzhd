import { SharedData } from '.';
import Pagination from './pagination';
import { InWatchlist } from './watchlist';

export interface Series {
    num: number;
    name: string;
    series_id: number;
    cover: string;
    plot: string;
    cast: string;
    director: string;
    genre: string;
    releaseDate: string;
    last_modified: string;
    rating: string;
    rating_5based: number;
    backdrop_path: string[];
    youtube_trailer: string;
    episode_run_time: string;
    category_id: string;
}

export interface SeriesInformation {
    seriesId: number;
    seasons: never[];
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
    backdropPath: string[];
    youtubeTrailer: string;
    episodeRunTime: string;
    categoryId: string;
    seasonsWithEpisodes: SeasonsWithEpisodes;
}

export interface SeasonsWithEpisodes {
    [seasonNumber: number]: SeasonWithEpisodes[];
}

export interface SeasonWithEpisodes {
    id: string;
    episodeNum: number;
    title: string;
    containerExtension: string;
    durationSecs: number;
    duration: string;
    video: Video;
    audio: Audio;
    bitrate: number;
    customSid: string;
    added: string;
    season: number;
    directSource: string;
}

export interface Video {
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
    disposition: Disposition;
    tags: VideoTags;
}

export interface Disposition {
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
}

export interface VideoTags {
    HANDLER_NAME: string;
    DURATION: string;
}

export interface Audio {
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
    disposition: Disposition;
    tags: AudioTags;
}

export interface AudioTags {
    language: string;
    DURATION: string;
}

export interface SeriesPageProps extends SharedData {
    series: Pagination<Series & InWatchlist>;
}

export interface SeriesInformationPageProps extends SharedData {
    num: number;
    series: SeriesInformation;
    in_watchlist: boolean;
}
