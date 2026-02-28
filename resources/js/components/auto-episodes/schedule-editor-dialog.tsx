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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { FormEvent, useEffect, useMemo, useState } from 'react';

type MonitorScheduleType = App.Enums.AutoEpisodes.MonitorScheduleType;
type SeriesMonitorData = App.Data.AutoEpisodes.SeriesMonitorData;

type ScheduleEditorDraft = {
    timezone: string;
    scheduleType: MonitorScheduleType;
    dailyTime: string;
    weeklyDays: number[];
    weeklyTime: string;
    monitoredSeasons: number[];
    perRunCap: string;
    backfillEnabled: boolean;
    backfillCount: number;
};

export type ScheduleEditorSubmitPayload = {
    monitor: {
        timezone: string;
        schedule_type: MonitorScheduleType;
        schedule_daily_time: string | null;
        schedule_weekly_days: number[];
        schedule_weekly_time: string | null;
        monitored_seasons: number[];
        per_run_cap?: number;
    };
    backfill_count?: number;
};

export interface ScheduleEditorDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'enable' | 'edit';
    monitor?: SeriesMonitorData | null;
    availableSeasons: number[];
    presetTimes: string[];
    backfillPresetCounts: number[];
    disabled?: boolean;
    disabledReason?: string;
    submitting?: boolean;
    onSubmit: (payload: ScheduleEditorSubmitPayload) => void;
}

const WEEKDAY_OPTIONS = [
    { value: 0, label: 'Sun' },
    { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' },
    { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
] as const;

const uniqueSorted = (values: number[]): number[] => [...new Set(values)].sort((a, b) => a - b);

const resolveBrowserTimezone = (): string => {
    try {
        const resolved = Intl.DateTimeFormat().resolvedOptions().timeZone;
        return typeof resolved === 'string' && resolved.trim() !== '' ? resolved : 'UTC';
    } catch {
        return 'UTC';
    }
};

const buildDraft = ({
    monitor,
    availableSeasons,
    defaultPresetTime,
    defaultBackfillCount,
    browserTimezone,
}: {
    monitor?: SeriesMonitorData | null;
    availableSeasons: number[];
    defaultPresetTime: string;
    defaultBackfillCount: number;
    browserTimezone: string;
}): ScheduleEditorDraft => {
    const monitoredSeasons = monitor?.monitored_seasons.length
        ? uniqueSorted(monitor.monitored_seasons)
        : uniqueSorted(availableSeasons);

    return {
        timezone: monitor?.timezone ?? browserTimezone,
        scheduleType: monitor?.schedule_type ?? 'hourly',
        dailyTime: monitor?.schedule_daily_time ?? defaultPresetTime,
        weeklyDays: uniqueSorted(monitor?.schedule_weekly_days ?? []),
        weeklyTime: monitor?.schedule_weekly_time ?? defaultPresetTime,
        monitoredSeasons,
        perRunCap: monitor?.per_run_cap ? String(monitor.per_run_cap) : '',
        backfillEnabled: false,
        backfillCount: defaultBackfillCount,
    };
};

export default function ScheduleEditorDialog({
    open,
    onOpenChange,
    mode,
    monitor,
    availableSeasons,
    presetTimes,
    backfillPresetCounts,
    disabled = false,
    disabledReason,
    submitting = false,
    onSubmit,
}: ScheduleEditorDialogProps) {
    const browserTimezone = useMemo(() => resolveBrowserTimezone(), []);
    const normalizedSeasons = useMemo(() => uniqueSorted(availableSeasons.filter((season) => season > 0)), [availableSeasons]);
    const normalizedPresetTimes = useMemo(() => {
        const filtered = presetTimes.filter((time) => time.trim() !== '');
        return filtered.length > 0 ? filtered : ['00:00'];
    }, [presetTimes]);
    const normalizedBackfillCounts = useMemo(() => {
        const filtered = uniqueSorted(backfillPresetCounts.filter((count) => count > 0));
        return filtered.length > 0 ? filtered : [5];
    }, [backfillPresetCounts]);
    const defaultPresetTime = normalizedPresetTimes[0];
    const defaultBackfillCount = normalizedBackfillCounts[0];
    const [draft, setDraft] = useState<ScheduleEditorDraft>(() =>
        buildDraft({
            monitor,
            availableSeasons: normalizedSeasons,
            defaultPresetTime,
            defaultBackfillCount,
            browserTimezone,
        }),
    );
    const [showAdvanced, setShowAdvanced] = useState(() => (monitor?.per_run_cap ?? null) !== null);
    const [submitError, setSubmitError] = useState<string | null>(null);
    const formLocked = disabled || submitting;

    useEffect(() => {
        if (!open) {
            return;
        }

        setDraft(
            buildDraft({
                monitor,
                availableSeasons: normalizedSeasons,
                defaultPresetTime,
                defaultBackfillCount,
                browserTimezone,
            }),
        );
        setShowAdvanced((monitor?.per_run_cap ?? null) !== null);
        setSubmitError(null);
    }, [
        open,
        monitor,
        mode,
        normalizedSeasons,
        defaultPresetTime,
        defaultBackfillCount,
        browserTimezone,
    ]);

    const toggleWeeklyDay = (day: number) => {
        setDraft((current) => ({
            ...current,
            weeklyDays: current.weeklyDays.includes(day)
                ? current.weeklyDays.filter((value) => value !== day)
                : uniqueSorted([...current.weeklyDays, day]),
        }));
    };

    const toggleSeason = (season: number) => {
        setDraft((current) => ({
            ...current,
            monitoredSeasons: current.monitoredSeasons.includes(season)
                ? current.monitoredSeasons.filter((value) => value !== season)
                : uniqueSorted([...current.monitoredSeasons, season]),
        }));
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (formLocked) {
            return;
        }

        if (draft.scheduleType === 'weekly' && draft.weeklyDays.length === 0) {
            setSubmitError('Weekly schedule requires at least one day.');
            return;
        }

        if (draft.scheduleType === 'daily' && draft.dailyTime.trim() === '') {
            setSubmitError('Daily schedule requires a preset time.');
            return;
        }

        if (draft.scheduleType === 'weekly' && draft.weeklyTime.trim() === '') {
            setSubmitError('Weekly schedule requires a preset time.');
            return;
        }

        const parsedPerRunCap = Number.parseInt(draft.perRunCap, 10);

        const payload: ScheduleEditorSubmitPayload = {
            monitor: {
                timezone: draft.timezone.trim() || browserTimezone,
                schedule_type: draft.scheduleType,
                schedule_daily_time: draft.scheduleType === 'daily' ? draft.dailyTime : null,
                schedule_weekly_days: draft.scheduleType === 'weekly' ? uniqueSorted(draft.weeklyDays) : [],
                schedule_weekly_time: draft.scheduleType === 'weekly' ? draft.weeklyTime : null,
                monitored_seasons: uniqueSorted(draft.monitoredSeasons),
                ...(Number.isFinite(parsedPerRunCap) && parsedPerRunCap > 0 ? { per_run_cap: parsedPerRunCap } : {}),
            },
            ...(mode === 'enable' && draft.backfillEnabled ? { backfill_count: draft.backfillCount } : {}),
        };

        setSubmitError(null);
        onSubmit(payload);
    };

    const title = mode === 'enable' ? 'Enable monitoring' : 'Edit monitoring';
    const submitLabel = mode === 'enable' ? 'Enable monitoring' : 'Save changes';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        Configure schedule, timezone, and season scope for this series monitor.
                    </DialogDescription>
                </DialogHeader>

                <form className="space-y-6" onSubmit={handleSubmit}>
                    {disabledReason ? (
                        <p className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">{disabledReason}</p>
                    ) : null}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="monitor-schedule-type">Schedule type</Label>
                            <Select
                                value={draft.scheduleType}
                                onValueChange={(value) => {
                                    setDraft((current) => ({ ...current, scheduleType: value as MonitorScheduleType }));
                                }}
                                disabled={formLocked}
                            >
                                <SelectTrigger id="monitor-schedule-type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="hourly">Hourly</SelectItem>
                                    <SelectItem value="daily">Daily</SelectItem>
                                    <SelectItem value="weekly">Weekly</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="monitor-timezone">Timezone</Label>
                            <Input
                                id="monitor-timezone"
                                value={draft.timezone}
                                onChange={(event) => setDraft((current) => ({ ...current, timezone: event.target.value }))}
                                placeholder="Africa/Cairo"
                                disabled={formLocked}
                            />
                        </div>
                    </div>

                    {draft.scheduleType === 'daily' ? (
                        <div className="space-y-2">
                            <Label htmlFor="monitor-daily-time">Daily preset time</Label>
                            <Select
                                value={draft.dailyTime}
                                onValueChange={(value) => setDraft((current) => ({ ...current, dailyTime: value }))}
                                disabled={formLocked}
                            >
                                <SelectTrigger id="monitor-daily-time">
                                    <SelectValue placeholder="Select time" />
                                </SelectTrigger>
                                <SelectContent>
                                    {normalizedPresetTimes.map((time) => (
                                        <SelectItem key={time} value={time}>
                                            {time}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    ) : null}

                    {draft.scheduleType === 'weekly' ? (
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <Label>Weekly days</Label>
                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    {WEEKDAY_OPTIONS.map((day) => {
                                        const checked = draft.weeklyDays.includes(day.value);

                                        return (
                                            <label
                                                key={day.value}
                                                className={cn(
                                                    'flex items-center gap-2 rounded-md border p-2 text-sm',
                                                    formLocked ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
                                                )}
                                            >
                                                <Checkbox
                                                    checked={checked}
                                                    onCheckedChange={() => toggleWeeklyDay(day.value)}
                                                    disabled={formLocked}
                                                />
                                                <span>{day.label}</span>
                                            </label>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="monitor-weekly-time">Weekly preset time</Label>
                                <Select
                                    value={draft.weeklyTime}
                                    onValueChange={(value) => setDraft((current) => ({ ...current, weeklyTime: value }))}
                                    disabled={formLocked}
                                >
                                    <SelectTrigger id="monitor-weekly-time">
                                        <SelectValue placeholder="Select time" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {normalizedPresetTimes.map((time) => (
                                            <SelectItem key={time} value={time}>
                                                {time}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    ) : null}

                    <div className="space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <Label>Monitored seasons</Label>

                            {normalizedSeasons.length > 0 ? (
                                <div className="flex items-center gap-2 text-xs">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setDraft((current) => ({ ...current, monitoredSeasons: normalizedSeasons }))}
                                        disabled={formLocked}
                                    >
                                        Select all
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setDraft((current) => ({ ...current, monitoredSeasons: [] }))}
                                        disabled={formLocked}
                                    >
                                        Clear
                                    </Button>
                                </div>
                            ) : null}
                        </div>

                        {normalizedSeasons.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No season metadata available yet.</p>
                        ) : (
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                {normalizedSeasons.map((season) => {
                                    const checked = draft.monitoredSeasons.includes(season);

                                    return (
                                        <label
                                            key={season}
                                            className={cn(
                                                'flex items-center gap-2 rounded-md border p-2 text-sm',
                                                formLocked ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
                                            )}
                                        >
                                            <Checkbox
                                                checked={checked}
                                                onCheckedChange={() => toggleSeason(season)}
                                                disabled={formLocked}
                                            />
                                            <span>Season {season}</span>
                                        </label>
                                    );
                                })}
                            </div>
                        )}

                        <p className="text-xs text-muted-foreground">No season selected means all seasons are monitored.</p>
                    </div>

                    <div className="space-y-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="px-0"
                            onClick={() => setShowAdvanced((current) => !current)}
                            disabled={formLocked}
                        >
                            {showAdvanced ? 'Hide advanced options' : 'Show advanced options'}
                        </Button>

                        {showAdvanced ? (
                            <div className="space-y-2 rounded-md border p-3">
                                <Label htmlFor="monitor-per-run-cap">Per-run cap (optional)</Label>
                                <Input
                                    id="monitor-per-run-cap"
                                    type="number"
                                    inputMode="numeric"
                                    min={1}
                                    value={draft.perRunCap}
                                    onChange={(event) =>
                                        setDraft((current) => ({
                                            ...current,
                                            perRunCap: event.target.value,
                                        }))
                                    }
                                    placeholder="Leave empty for default"
                                    disabled={formLocked}
                                />
                            </div>
                        ) : null}
                    </div>

                    {mode === 'enable' ? (
                        <div className="space-y-3 rounded-md border p-3">
                            <Label className="text-sm font-medium">Optional backfill</Label>
                            <label
                                className={cn(
                                    'flex items-center gap-2 rounded-md border border-dashed p-2 text-sm',
                                    formLocked ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
                                )}
                            >
                                <Checkbox
                                    checked={draft.backfillEnabled}
                                    onCheckedChange={(checked) =>
                                        setDraft((current) => ({
                                            ...current,
                                            backfillEnabled: checked === true,
                                        }))
                                    }
                                    disabled={formLocked}
                                />
                                <span>Backfill recent episodes after enabling</span>
                            </label>

                            {draft.backfillEnabled ? (
                                <div className="space-y-2">
                                    <Label htmlFor="monitor-backfill-count">Backfill count</Label>
                                    <Select
                                        value={String(draft.backfillCount)}
                                        onValueChange={(value) => {
                                            setDraft((current) => ({
                                                ...current,
                                                backfillCount: Number.parseInt(value, 10) || defaultBackfillCount,
                                            }));
                                        }}
                                        disabled={formLocked}
                                    >
                                        <SelectTrigger id="monitor-backfill-count">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {normalizedBackfillCounts.map((count) => (
                                                <SelectItem key={count} value={String(count)}>
                                                    {count} episodes
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            ) : null}
                        </div>
                    ) : null}

                    {submitError ? <p className="text-sm text-destructive">{submitError}</p> : null}

                    <DialogFooter>
                        <Button type="button" variant="secondary" onClick={() => onOpenChange(false)} disabled={submitting}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={formLocked}>
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
