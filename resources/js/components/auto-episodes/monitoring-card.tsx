import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
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
import { useEffect, useMemo, useState } from 'react';

type SeriesMonitorData = App.Data.AutoEpisodes.SeriesMonitorData;

export interface MonitoringCardProps {
    monitor: SeriesMonitorData | null;
    inWatchlist: boolean;
    canManage: boolean;
    disabledReason?: string;
    processing?: boolean;
    onEnable: () => void;
    onEdit: () => void;
    onRunNow: () => void;
    onDisable: (removeFromWatchlist: boolean) => void;
}

const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as const;

const formatDateTime = (value?: string): string => {
    if (!value) {
        return '—';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return parsed.toLocaleString();
};

const formatCooldown = (milliseconds: number): string => {
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

const scheduleSummary = (monitor: SeriesMonitorData | null): string => {
    if (!monitor || !monitor.enabled) {
        return 'Monitoring is disabled for this series.';
    }

    if (monitor.schedule_type === 'hourly') {
        return `Hourly (top of hour) · ${monitor.timezone}`;
    }

    if (monitor.schedule_type === 'daily') {
        return `Daily at ${monitor.schedule_daily_time ?? 'preset'} · ${monitor.timezone}`;
    }

    const days = monitor.schedule_weekly_days
        .map((day) => WEEKDAY_LABELS[day] ?? String(day))
        .join(', ');

    return `Weekly on ${days || 'selected days'} at ${monitor.schedule_weekly_time ?? 'preset'} · ${monitor.timezone}`;
};

const statusVariant = (
    status?: App.Enums.AutoEpisodes.SeriesMonitorRunStatus,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'success') {
        return 'default';
    }

    if (status === 'success_with_warnings') {
        return 'secondary';
    }

    if (status === 'failed') {
        return 'destructive';
    }

    return 'outline';
};

export default function MonitoringCard({
    monitor,
    inWatchlist,
    canManage,
    disabledReason,
    processing = false,
    onEnable,
    onEdit,
    onRunNow,
    onDisable,
}: MonitoringCardProps) {
    const [disableDialogOpen, setDisableDialogOpen] = useState(false);
    const [removeFromWatchlist, setRemoveFromWatchlist] = useState(false);
    const [nowMs, setNowMs] = useState(() => Date.now());
    const runNowAvailableAtMs = useMemo(() => {
        if (!monitor?.run_now_available_at) {
            return null;
        }

        const parsed = Date.parse(monitor.run_now_available_at);
        return Number.isNaN(parsed) ? null : parsed;
    }, [monitor?.run_now_available_at]);

    useEffect(() => {
        if (runNowAvailableAtMs === null || runNowAvailableAtMs <= Date.now()) {
            return;
        }

        const interval = window.setInterval(() => {
            setNowMs(Date.now());
        }, 1000);

        return () => {
            window.clearInterval(interval);
        };
    }, [runNowAvailableAtMs]);

    const isEnabled = monitor?.enabled ?? false;
    const runNowRemainingMs = runNowAvailableAtMs === null ? 0 : Math.max(0, runNowAvailableAtMs - nowMs);
    const runNowCoolingDown = runNowRemainingMs > 0;
    const controlsLocked = !canManage || processing;
    const enableDisabledReason = !inWatchlist
        ? 'Add this series to your watchlist before enabling monitoring.'
        : canManage
          ? undefined
          : disabledReason;

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="space-y-1">
                        <CardTitle>Monitoring</CardTitle>
                        <CardDescription>{scheduleSummary(monitor)}</CardDescription>
                    </div>

                    <Badge variant={isEnabled ? 'default' : 'secondary'}>{isEnabled ? 'Enabled' : 'Disabled'}</Badge>
                </div>
            </CardHeader>

            <CardContent className="space-y-4">
                {!canManage && disabledReason ? (
                    <p className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">{disabledReason}</p>
                ) : null}

                {!isEnabled && inWatchlist ? (
                    <p className="text-sm text-muted-foreground">Enable monitoring to start automatic episode scans for this series.</p>
                ) : null}

                {!isEnabled && !inWatchlist ? (
                    <p className="text-sm text-muted-foreground">
                        Add this series to your watchlist first, then enable monitoring with a schedule.
                    </p>
                ) : null}

                {isEnabled ? (
                    <>
                        <div className="grid gap-3 text-sm sm:grid-cols-2">
                            <div className="space-y-1">
                                <p className="text-xs font-medium uppercase text-muted-foreground">Last run</p>
                                <p>{formatDateTime(monitor?.last_attempt_at)}</p>
                            </div>

                            <div className="space-y-1">
                                <p className="text-xs font-medium uppercase text-muted-foreground">Next run</p>
                                <p>{formatDateTime(monitor?.next_run_at)}</p>
                            </div>
                        </div>

                        {monitor?.last_attempt_status ? (
                            <div className="space-y-1">
                                <p className="text-xs font-medium uppercase text-muted-foreground">Last run status</p>
                                <Badge variant={statusVariant(monitor.last_attempt_status)} className="capitalize">
                                    {monitor.last_attempt_status.replaceAll('_', ' ')}
                                </Badge>
                            </div>
                        ) : null}

                        {runNowCoolingDown ? (
                            <p className="text-sm text-muted-foreground">Run now available in {formatCooldown(runNowRemainingMs)}.</p>
                        ) : null}
                    </>
                ) : null}
            </CardContent>

            <CardFooter className="flex flex-wrap gap-2">
                {!isEnabled ? (
                    <Button onClick={onEnable} disabled={controlsLocked || !inWatchlist} title={enableDisabledReason}>
                        Enable monitoring
                    </Button>
                ) : (
                    <>
                        <Button onClick={onRunNow} disabled={controlsLocked || runNowCoolingDown}>
                            Run now
                        </Button>
                        <Button variant="outline" onClick={onEdit} disabled={controlsLocked}>
                            Edit schedule
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => setDisableDialogOpen(true)}
                            disabled={controlsLocked}
                        >
                            Disable
                        </Button>
                    </>
                )}
            </CardFooter>

            <Dialog
                open={disableDialogOpen}
                onOpenChange={(open) => {
                    setDisableDialogOpen(open);
                    if (!open) {
                        setRemoveFromWatchlist(false);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Disable monitoring?</DialogTitle>
                        <DialogDescription>Choose whether to only disable monitoring or also remove this series from watchlist.</DialogDescription>
                    </DialogHeader>

                    <label className="flex items-center gap-2 rounded-md border p-2 text-sm">
                        <Checkbox
                            checked={removeFromWatchlist}
                            onCheckedChange={(checked) => setRemoveFromWatchlist(checked === true)}
                            disabled={processing}
                        />
                        <Label>Also remove from watchlist</Label>
                    </label>

                    <DialogFooter>
                        <Button variant="secondary" onClick={() => setDisableDialogOpen(false)} disabled={processing}>
                            Keep enabled
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                onDisable(removeFromWatchlist);
                                setDisableDialogOpen(false);
                                setRemoveFromWatchlist(false);
                            }}
                            disabled={processing}
                        >
                            Confirm disable
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Card>
    );
}
