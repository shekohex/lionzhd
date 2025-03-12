import { SharedData } from '.';
import Pagination from './pagination';

export interface VodStream {
    num: number;
    name: string;
    stream_type: string;
    stream_id: number;
    stream_icon: string;
    rating: string;
    rating_5based: number;
    added: string;
    is_adult: boolean;
    category_id: string;
    container_extension: string;
    custom_sid: string;
    direct_source: string;
}

export interface VodStreamInformation {
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
    backdropPath: string[];
    durationSecs: number;
    duration: string;
    video: Video;
    audio: Audio;
    bitrate: number;
    movie: Movie;
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

export interface AudioTags {
    language: string;
    DURATION: string;
}

export interface Movie {
    streamId: number;
    name: string;
    added: string;
    categoryId: string;
    containerExtension: string;
    customSid: string;
    directSource: string;
}

export interface MoviesPageProps extends SharedData {
    movies: Pagination<VodStream>;
}

export interface MovieInformationPageProps extends SharedData {
    movie: VodStreamInformation;
    in_watchlist: boolean;
}
