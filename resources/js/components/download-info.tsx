import ResponsiveImage from '@/components/responsive-image';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Pause, Play, Redo, Trash2, X } from 'lucide-react';

interface DownloadInformationProps {
    download: App.Data.MediaDownloadRefData;
    highlighted?: boolean;
    onPause?: () => void;
    onResume?: () => void;
    onRetry?: () => void;
    onRemove?: () => void;
    onCancel?: () => void;
}

const DownloadInformation: React.FC<DownloadInformationProps> = ({
    download,
    highlighted,
    onCancel,
    onPause,
    onResume,
    onRetry,
    onRemove,
}) => {
    const downloadPercentage =
        (download.downloadStatus &&
            Math.round((download.downloadStatus.completedLength / download.downloadStatus.totalLength) * 100)) ??
        0;
    const percentage = downloadPercentage || 0;
    const totalBytes = download.downloadStatus ? download.downloadStatus.totalLength : 0;
    const title = download.media.name;
    const movie = download.media_type === 'movie' ? (download.media as App.Data.VodStreamData) : null;
    const series = download.media_type === 'series' ? (download.media as App.Data.SeriesData) : null;

    const backdropUrl = movie?.stream_icon || series?.cover;
    const posterUrl = movie?.stream_icon || series?.cover;
    const additionalBackdrops = series?.backdrop_path || [];

    const formatBytes = (bytes: number, decimals: number = 2) => {
        if (!+bytes) return '0 Bytes';

        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
    };

    return (
        <motion.div
            className="flex items-center justify-between"
            initial={true}
            animate={
                highlighted
                    ? {
                          backgroundColor: [
                              'oklch(from var(--muted-foreground) 0.3 c h)',
                              'oklch(from var(--muted-foreground) 0.0 c h)',
                          ],
                          transition: { duration: 3, ease: 'easeInOut' },
                      }
                    : {}
            }
        >
            <div className="flex items-center gap-4">
                <Link
                    preserveState={false}
                    href={route(download.media_type === 'movie' ? 'movies.show' : 'series.show', {
                        model: download.media_id,
                    })}
                >
                    <ResponsiveImage
                        src={backdropUrl}
                        fallbackSrc={posterUrl}
                        additionalFallbacks={additionalBackdrops}
                        alt={title}
                        className="h-16 w-16"
                        aspectRatio=""
                        showSkeleton={true}
                        showPlaceholder={true}
                        placeholderClassName="h-full w-full bg-gradient-to-b from-muted/20 to-muted"
                    />
                </Link>
                <div>
                    <h3 className="font-semibold">{download.media.name}</h3>
                    {download.media_type === 'series' && download.episode !== null && download.episode !== undefined ? (
                        <p className="text-muted-foreground text-sm">
                            S{download.season}E{download.episode} - {download.media.name}
                        </p>
                    ) : null}
                </div>
            </div>
            <div className="flex items-center gap-2">
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span className="text-sm">{percentage}%</span>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p className="text-sm">
                                {download.downloadStatus?.completedLength} / {download.downloadStatus?.totalLength}
                            </p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
                <span className="text-sm">{formatBytes(totalBytes)}</span>
                {download.downloadStatus?.status === 'error' ? (
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <span className="text-sm text-red-500">Error</span>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p className="text-sm">{download.downloadStatus.errorMessage}</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                ) : (
                    <span className="text-sm">{download.downloadStatus?.status ?? 'N/A'}</span>
                )}
                <div className="flex items-center gap-2">
                    {download.downloadStatus?.status === 'active' && (
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        onClick={() => onPause?.()}
                                        className="hover:bg-muted rounded-md p-1"
                                    >
                                        <Pause className="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p className="text-sm">Pause Download</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    )}
                    {download.downloadStatus?.status === 'paused' && (
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        onClick={() => onResume?.()}
                                        className="hover:bg-muted rounded-md p-1"
                                    >
                                        <Play className="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p className="text-sm">Resume Download</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    )}
                    {download.downloadStatus?.status === 'error' && (
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        onClick={() => onRetry?.()}
                                        className="hover:bg-muted rounded-md p-1"
                                    >
                                        <Redo className="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p className="text-sm">Retry Download</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    )}
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    onClick={() => onRemove?.()}
                                    className="hover:bg-muted rounded-md p-1"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p className="text-sm">Remove Download</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>

                    {download.downloadStatus?.status !== 'complete' && (
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button variant="destructive" size="icon" onClick={() => onCancel?.()}>
                                        <X className="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p className="text-sm">Abort Download</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    )}
                </div>
            </div>
        </motion.div>
    );
};

export default DownloadInformation;
