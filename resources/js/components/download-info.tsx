import ResponsiveImage from '@/components/responsive-image';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Loader2, Pause, Play, Redo, Trash2, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface DownloadInformationProps {
    download: App.Data.MediaDownloadRefData;
    highlighted?: boolean;
    isAdminViewer?: boolean;
    currentUserId?: number;
    isReadOnly?: boolean;
    readonlyReason?: string;
    onReadonlyAction?: () => void;
    onPause?: () => void;
    onResume?: () => void;
    onRetry?: () => void;
    onRemove?: () => void;
    onCancel?: (deletePartial: boolean) => void;
}

const clamp = (value: number, min: number, max: number): number => Math.min(max, Math.max(min, value));

const formatBytes = (bytes: number, decimals: number = 2): string => {
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '0 Bytes';
    }

    const units = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];
    const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    const value = bytes / 1024 ** exponent;

    return `${value.toFixed(Math.max(0, decimals)).replace(/\.00$/, '')} ${units[exponent]}`;
};

const formatRetryCountdown = (milliseconds: number): string => {
    const totalSeconds = Math.max(0, Math.ceil(milliseconds / 1000));
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    if (minutes >= 60) {
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        return `${hours}h ${remainingMinutes}m`;
    }

    if (minutes > 0) {
        return `${minutes}m ${seconds}s`;
    }

    return `${seconds}s`;
};

const getFriendlyFailureSummary = (message: string | null, code?: number): string => {
    const normalized = message?.toLowerCase() ?? '';

    if (normalized.includes('timeout') || normalized.includes('timed out')) {
        return 'Connection timed out while downloading. Try again.';
    }

    if (normalized.includes('network') || normalized.includes('connection')) {
        return 'Network issue detected. Retry when your connection is stable.';
    }

    if (normalized.includes('forbidden') || normalized.includes('unauthorized') || code === 401 || code === 403) {
        return 'Source access is denied. Retry later or contact an administrator.';
    }

    if (normalized.includes('not found') || code === 404) {
        return 'Source file is currently unavailable. Retry later.';
    }

    return 'Download failed. Retry when ready.';
};

const DownloadInformation = ({
    download,
    highlighted,
    isAdminViewer = false,
    currentUserId,
    isReadOnly = false,
    readonlyReason,
    onReadonlyAction,
    onCancel,
    onPause,
    onResume,
    onRetry,
    onRemove,
}: DownloadInformationProps) => {
    const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
    const [deletePartial, setDeletePartial] = useState(false);
    const [nowMs, setNowMs] = useState(() => Date.now());

    const status = download.downloadStatus;
    const hasHydratedStatus = status !== undefined;
    const totalLength = Math.max(0, status?.totalLength ?? 0);
    const completedLength = Math.max(0, status?.completedLength ?? 0);
    const downloadSpeed = Math.max(0, status?.downloadSpeed ?? 0);
    const hasReliableTotal = totalLength > 0;
    const percentValue = hasReliableTotal
        ? clamp((completedLength / Math.max(totalLength, 1)) * 100, 0, 100)
        : null;
    const percentLabel = percentValue === null ? null : `${percentValue.toFixed(1)}%`;
    const speedLabel = downloadSpeed > 0 ? `${formatBytes(downloadSpeed)}/s` : null;

    const retryNextAtMs = useMemo(() => {
        if (!download.retry_next_at) {
            return null;
        }

        const parsed = Date.parse(download.retry_next_at);
        return Number.isNaN(parsed) ? null : parsed;
    }, [download.retry_next_at]);

    useEffect(() => {
        if (retryNextAtMs === null || retryNextAtMs <= Date.now()) {
            return;
        }

        const interval = window.setInterval(() => {
            setNowMs(Date.now());
        }, 1000);

        return () => {
            window.clearInterval(interval);
        };
    }, [retryNextAtMs]);

    const retryCountdownMs = retryNextAtMs === null ? null : retryNextAtMs - nowMs;
    const isCoolingDown = retryCountdownMs !== null && retryCountdownMs > 0;
    const isCanceled = download.canceled_at !== undefined && download.canceled_at !== null;
    const isStarting =
        hasHydratedStatus &&
        !isCanceled &&
        !hasReliableTotal &&
        completedLength <= 0 &&
        downloadSpeed <= 0 &&
        (status?.status === 'active' || status?.status === 'waiting');

    const technicalErrorMessage = download.last_error_message?.trim() || status?.errorMessage?.trim() || null;
    const errorCode = download.last_error_code ?? (status && status.errorCode > 0 ? status.errorCode : undefined);
    const hasFailureSignal =
        status?.status === 'error' || technicalErrorMessage !== null || (errorCode !== undefined && errorCode > 0);
    const isTerminalFailed = !isCanceled && !isCoolingDown && hasFailureSignal;
    const friendlyFailureSummary = getFriendlyFailureSummary(technicalErrorMessage, errorCode);

    const statusLabel = useMemo(() => {
        if (isCanceled) {
            return 'Canceled';
        }

        if (isCoolingDown) {
            const countdown = formatRetryCountdown(retryCountdownMs ?? 0);
            const attempt = download.retry_attempt > 0 ? ` (attempt ${download.retry_attempt})` : '';
            return `Retrying in ${countdown}${attempt}`;
        }

        if (isTerminalFailed) {
            return 'Failed';
        }

        if (isStarting) {
            return 'Starting...';
        }

        if (status?.status === 'active') {
            return 'Downloading';
        }

        if (status?.status === 'paused' || download.desired_paused) {
            return 'Paused';
        }

        if (status?.status === 'waiting') {
            return 'Queued';
        }

        if (status?.status === 'complete') {
            return 'Complete';
        }

        if (status?.status === 'removed') {
            return 'Removed';
        }

        if (!hasHydratedStatus) {
            return 'Refreshing...';
        }

        return status?.status ?? 'Unknown';
    }, [
        download.desired_paused,
        download.retry_attempt,
        hasHydratedStatus,
        isCanceled,
        isCoolingDown,
        isStarting,
        isTerminalFailed,
        retryCountdownMs,
        status?.status,
    ]);

    const title = download.media.name;
    const ownerLabel = download.owner
        ? `${download.owner.name} (${download.owner.email})`
        : download.user_id
          ? `User #${download.user_id}`
          : 'Unknown';
    const isMine = currentUserId !== undefined && download.user_id === currentUserId;
    const movie = download.media_type === 'movie' ? (download.media as App.Data.VodStreamData) : null;
    const series = download.media_type === 'series' ? (download.media as App.Data.SeriesData) : null;

    const backdropUrl = movie?.stream_icon || series?.cover;
    const posterUrl = movie?.stream_icon || series?.cover;
    const additionalBackdrops = series?.backdrop_path || [];
    const buttonClassName = isReadOnly ? 'cursor-not-allowed rounded-md p-1 opacity-50' : 'hover:bg-muted rounded-md p-1';

    const statusBadgeVariant = isCanceled || isTerminalFailed ? 'destructive' : statusLabel === 'Complete' ? 'default' : 'secondary';
    const cancelCheckboxId = `cancel-delete-partial-${download.id}`;
    const showPauseAction = !isCanceled && status?.status === 'active' && !download.desired_paused;
    const showResumeAction = !isCanceled && (status?.status === 'paused' || download.desired_paused);
    const showRetryAction = !isCanceled && (isTerminalFailed || isCoolingDown);
    const showCancelAction = !isCanceled && status?.status !== 'complete';
    const showActions = !isCanceled;

    const handleReadOnlyAwareAction = (action?: () => void) => {
        if (isReadOnly) {
            onReadonlyAction?.();
            return;
        }

        action?.();
    };

    const closeCancelDialog = () => {
        setCancelDialogOpen(false);
        setDeletePartial(false);
    };

    const openCancelDialog = () => {
        handleReadOnlyAwareAction(() => {
            setDeletePartial(false);
            setCancelDialogOpen(true);
        });
    };

    const confirmCancel = () => {
        handleReadOnlyAwareAction(() => {
            onCancel?.(deletePartial);
            closeCancelDialog();
        });
    };

    const retryDisabled = isReadOnly || isCoolingDown;
    const retryTooltip = isCoolingDown
        ? `Retry available in ${formatRetryCountdown(retryCountdownMs ?? 0)}`
        : 'Retry download';

    return (
        <motion.div
            className="flex items-center justify-between gap-4"
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
                <div className="min-w-0">
                    <h3 className="font-semibold">{download.media.name}</h3>
                    {isAdminViewer ? (
                        <div className="mt-1 flex flex-wrap items-center gap-2">
                            <Badge variant="outline" className="text-xs">
                                Owner: {ownerLabel}
                            </Badge>
                            {isMine ? <span className="text-muted-foreground text-xs">Mine</span> : null}
                        </div>
                    ) : null}
                    {download.media_type === 'series' && download.episode !== null && download.episode !== undefined ? (
                        <p className="text-muted-foreground text-sm">
                            S{download.season}E{download.episode} - {download.media.name}
                        </p>
                    ) : null}
                </div>
            </div>

            <div className="flex flex-col items-end gap-2">
                {!isCanceled && !hasHydratedStatus ? (
                    <div className="flex items-center gap-2">
                        <Skeleton className="h-4 w-16" />
                        <Skeleton className="h-4 w-28" />
                        <Skeleton className="h-4 w-20" />
                    </div>
                ) : null}

                {!isCanceled && hasHydratedStatus && isStarting ? <span className="text-sm">Starting...</span> : null}

                {!isCanceled && hasHydratedStatus && !isStarting ? (
                    <div className="flex items-center gap-2 text-sm">
                        {percentLabel ? <span className="font-medium tabular-nums">{percentLabel}</span> : null}
                        {!percentLabel ? <Loader2 className="text-muted-foreground h-3.5 w-3.5 animate-spin" /> : null}
                        <span className="text-muted-foreground tabular-nums">
                            {hasReliableTotal
                                ? `${formatBytes(completedLength)} / ${formatBytes(totalLength)}`
                                : `${formatBytes(completedLength)} downloaded`}
                        </span>
                        {speedLabel ? <span className="text-muted-foreground tabular-nums">{speedLabel}</span> : null}
                    </div>
                ) : null}

                <Badge variant={statusBadgeVariant}>{statusLabel}</Badge>

                {isTerminalFailed ? (
                    <div className="max-w-sm rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                        <p>{friendlyFailureSummary}</p>
                        {isAdminViewer ? (
                            <details className="mt-2 text-xs">
                                <summary className="cursor-pointer font-medium">Technical details</summary>
                                <div className="mt-1 space-y-1 font-mono text-[11px]">
                                    {errorCode !== undefined ? <p>Code: {errorCode}</p> : null}
                                    {technicalErrorMessage ? <p>{technicalErrorMessage}</p> : null}
                                </div>
                            </details>
                        ) : null}
                    </div>
                ) : null}

                {isReadOnly && readonlyReason ? (
                    <span className="text-muted-foreground max-w-xs text-right text-xs">{readonlyReason}</span>
                ) : null}

                {showActions ? (
                    <div className="flex items-center gap-2">
                        {showPauseAction ? (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            onClick={() => handleReadOnlyAwareAction(onPause)}
                                            className={buttonClassName}
                                            aria-disabled={isReadOnly}
                                            title={isReadOnly ? readonlyReason : undefined}
                                        >
                                            <Pause className="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p className="text-sm">Pause download</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        ) : null}

                        {showResumeAction ? (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            onClick={() => handleReadOnlyAwareAction(onResume)}
                                            className={buttonClassName}
                                            aria-disabled={isReadOnly}
                                            title={isReadOnly ? readonlyReason : undefined}
                                        >
                                            <Play className="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p className="text-sm">Resume download</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        ) : null}

                        {showRetryAction ? (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            onClick={() => handleReadOnlyAwareAction(onRetry)}
                                            className={buttonClassName}
                                            disabled={retryDisabled}
                                            aria-disabled={retryDisabled}
                                            title={isReadOnly ? readonlyReason : undefined}
                                        >
                                            <Redo className="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p className="text-sm">{retryTooltip}</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        ) : null}

                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        onClick={() => handleReadOnlyAwareAction(onRemove)}
                                        className={buttonClassName}
                                        aria-disabled={isReadOnly}
                                        title={isReadOnly ? readonlyReason : undefined}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p className="text-sm">Remove download</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>

                        {showCancelAction ? (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="destructive"
                                            size="icon"
                                            onClick={openCancelDialog}
                                            className={isReadOnly ? 'cursor-not-allowed opacity-50' : undefined}
                                            aria-disabled={isReadOnly}
                                            title={isReadOnly ? readonlyReason : undefined}
                                        >
                                            <X className="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p className="text-sm">Cancel download</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        ) : null}
                    </div>
                ) : null}

                <Dialog
                    open={cancelDialogOpen}
                    onOpenChange={(open) => {
                        if (open) {
                            setCancelDialogOpen(true);
                            return;
                        }

                        closeCancelDialog();
                    }}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Cancel this download?</DialogTitle>
                            <DialogDescription>
                                {title} will stop immediately.
                                {isAdminViewer ? ` Owner: ${ownerLabel}` : ''}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id={cancelCheckboxId}
                                checked={deletePartial}
                                onCheckedChange={(checked) => setDeletePartial(checked === true)}
                            />
                            <Label htmlFor={cancelCheckboxId}>Delete partial data</Label>
                        </div>

                        <DialogFooter className="gap-2">
                            <Button variant="secondary" onClick={closeCancelDialog}>
                                Keep download
                            </Button>
                            <Button variant="destructive" onClick={confirmCancel}>
                                Confirm cancel
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </motion.div>
    );
};

export default DownloadInformation;
