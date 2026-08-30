import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { LoaderCircle, Maximize, Play, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

export interface VideoPlayerSource {
    url: string;
    title: string;
    containerExtension: string;
}

interface VideoPlayerProps {
    source: VideoPlayerSource | null;
    onClose: () => void;
    nextTitle?: string | null;
    onPlayNext?: () => void;
    className?: string;
}

function playbackErrorMessage(error: MediaError | null, containerExtension: string, isHls: boolean): string {
    const extension = containerExtension.toLowerCase();

    if (extension === 'mkv' || extension === 'hevc' || extension === 'h265') {
        return `This browser cannot play the provider's ${extension.toUpperCase()} format or codec. Try another browser or device.`;
    }

    if (isHls) {
        return 'HLS playback failed. The provider may not allow browser playback from this device (CORS), or the stream may be unavailable.';
    }

    if (error?.code === MediaError.MEDIA_ERR_SRC_NOT_SUPPORTED) {
        return `This browser does not support the provider's ${extension.toUpperCase() || 'media'} container or codec.`;
    }

    return 'Playback failed. The provider stream may be unavailable or use a format this browser does not support.';
}

export default function VideoPlayer({ source, onClose, nextTitle, onPlayNext, className }: VideoPlayerProps) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [nextCountdown, setNextCountdown] = useState<number | null>(null);
    const isHls = useMemo(() => source?.containerExtension.toLowerCase() === 'm3u8', [source]);

    const togglePlayback = useCallback(() => {
        const video = videoRef.current;
        if (!video) return;

        if (video.paused) {
            void video.play();
        } else {
            video.pause();
        }
    }, []);

    const playNext = useCallback(() => {
        setNextCountdown(null);
        onPlayNext?.();
    }, [onPlayNext]);

    useEffect(() => {
        if (nextCountdown === null) return;

        if (nextCountdown <= 0) {
            playNext();
            return;
        }

        const timeout = window.setTimeout(() => setNextCountdown((count) => (count === null ? null : count - 1)), 1000);
        return () => window.clearTimeout(timeout);
    }, [nextCountdown, playNext]);

    useEffect(() => {
        const video = videoRef.current;
        if (!source || !video) return;

        let disposed = false;
        let destroyHls: (() => void) | undefined;

        setLoading(true);
        setError(null);
        setNextCountdown(null);

        if (!isHls || video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = source.url;
            video.load();
            void video.play().catch(() => undefined);
        } else {
            void import('hls.js')
                .then(({ default: Hls }) => {
                    if (disposed) return;

                    if (!Hls.isSupported()) {
                        setLoading(false);
                        setError('HLS playback is not supported by this browser.');
                        return;
                    }

                    const hls = new Hls({ enableWorker: true });
                    destroyHls = () => hls.destroy();
                    hls.loadSource(source.url);
                    hls.attachMedia(video);
                    hls.on(Hls.Events.MANIFEST_PARSED, () => {
                        void video.play().catch(() => undefined);
                    });
                    hls.on(Hls.Events.ERROR, (_event, data) => {
                        if (data.fatal) {
                            setLoading(false);
                            setError(playbackErrorMessage(video.error, source.containerExtension, true));
                        }
                    });
                })
                .catch(() => {
                    if (!disposed) {
                        setLoading(false);
                        setError('The HLS player could not be loaded on this device.');
                    }
                });
        }

        video.focus();

        return () => {
            disposed = true;
            destroyHls?.();
            video.pause();
            video.removeAttribute('src');
            video.load();
        };
    }, [isHls, source]);

    useEffect(() => {
        if (!source) return;

        const handleKeyDown = (event: KeyboardEvent) => {
            const video = videoRef.current;
            if (!video) return;

            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                video.currentTime = Math.max(0, video.currentTime - 10);
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                video.currentTime = Math.min(video.duration || Number.POSITIVE_INFINITY, video.currentTime + 10);
            } else if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                togglePlayback();
            } else if (event.key === 'Escape' && !document.fullscreenElement) {
                event.preventDefault();
                onClose();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [onClose, source, togglePlayback]);

    if (!source) return null;

    const enterFullscreen = () => {
        const video = videoRef.current;
        if (video?.requestFullscreen) {
            void video.requestFullscreen();
        }
    };

    return (
        <div className={cn('fixed inset-0 z-[100] flex flex-col bg-black', className)} role="dialog" aria-modal="true">
            <div className="flex min-h-14 items-center justify-between gap-3 bg-black/90 px-3 py-2 text-white sm:px-5">
                <h2 className="min-w-0 truncate text-sm font-medium sm:text-base">{source.title}</h2>
                <div className="flex shrink-0 items-center gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={enterFullscreen}
                        className="text-white hover:bg-white/15 hover:text-white"
                    >
                        <Maximize className="h-4 w-4" />
                        <span className="hidden sm:inline">Fullscreen</span>
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={onClose}
                        aria-label="Close player"
                        className="text-white hover:bg-white/15 hover:text-white"
                    >
                        <X className="h-5 w-5" />
                    </Button>
                </div>
            </div>

            <div className="relative flex min-h-0 flex-1 items-center justify-center bg-black">
                <video
                    ref={videoRef}
                    controls
                    autoPlay
                    playsInline
                    preload="metadata"
                    className="h-full w-full bg-black object-contain outline-none"
                    aria-label={`Playing ${source.title}`}
                    onLoadStart={() => setLoading(true)}
                    onCanPlay={() => setLoading(false)}
                    onPlaying={() => setLoading(false)}
                    onWaiting={() => setLoading(true)}
                    onError={() => {
                        setLoading(false);
                        setError(
                            playbackErrorMessage(videoRef.current?.error ?? null, source.containerExtension, isHls),
                        );
                    }}
                    onEnded={() => {
                        if (nextTitle && onPlayNext) setNextCountdown(5);
                    }}
                />

                {loading && !error && (
                    <div className="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/35 text-white">
                        <div className="flex items-center gap-2 rounded-md bg-black/70 px-4 py-3 text-sm">
                            <LoaderCircle className="h-5 w-5 animate-spin" /> Loading video…
                        </div>
                    </div>
                )}

                {error && (
                    <div className="absolute inset-0 flex items-center justify-center bg-black/80 p-6 text-center text-white">
                        <div className="max-w-xl space-y-4">
                            <h3 className="text-lg font-semibold">Unable to play this video</h3>
                            <p className="text-sm text-white/75">{error}</p>
                            <Button variant="secondary" onClick={onClose}>
                                Close player
                            </Button>
                        </div>
                    </div>
                )}

                {nextCountdown !== null && nextTitle && (
                    <div className="absolute inset-0 flex items-center justify-center bg-black/75 p-6 text-center text-white">
                        <div className="max-w-lg space-y-4">
                            <p className="text-sm text-white/70">Up next in {nextCountdown} seconds</p>
                            <h3 className="text-xl font-semibold">{nextTitle}</h3>
                            <div className="flex flex-wrap justify-center gap-3">
                                <Button onClick={playNext}>
                                    <Play className="h-4 w-4" /> Play now
                                </Button>
                                <Button variant="secondary" onClick={() => setNextCountdown(null)}>
                                    Cancel
                                </Button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
