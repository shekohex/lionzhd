import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Link } from '@inertiajs/react';

interface DownloadInformationProps {
    download: App.Data.MediaDownloadRefData;
}

const DownloadInformation: React.FC<DownloadInformationProps> = ({ download }) => {
    const downloadPercentage =
        (download.downloadStatus &&
            Math.round((download.downloadStatus.completedLength / download.downloadStatus.totalLength) * 100)) ??
        0;
    const percentage = downloadPercentage || 0;
    const totalBytes = download.downloadStatus ? download.downloadStatus.totalLength : 0;

    const formatBytes = (bytes: number, decimals: number = 2) => {
        if (!+bytes) return '0 Bytes';

        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
    };

    return (
        <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
                <Link
                    href={route(download.media_type === 'movie' ? 'movies.show' : 'series.show', {
                        model: download.media_id,
                    })}
                >
                    {/* TODO: Implement Media Info Component */}
                    <img
                        src={
                            download.media_type === 'series'
                                ? (download.media as App.Data.SeriesData).cover
                                : (download.media as App.Data.VodStreamData).stream_icon
                        }
                        alt={download.media.name}
                        className="h-16 w-16 rounded-md object-cover"
                    />
                </Link>
                <div>
                    <h3 className="font-semibold">{download.media.name}</h3>
                    {download.media_type === 'series' && download.episode !== null && download.episode !== undefined ? (
                        <p className="text-muted-foreground text-sm">Episode: {download.episode + 1}</p>
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
                    <span className="text-sm">{download.downloadStatus?.status}</span>
                )}
            </div>
        </div>
    );
};

export default DownloadInformation;
