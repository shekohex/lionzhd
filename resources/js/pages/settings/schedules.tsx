import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import ScheduleEditorDialog, { type ScheduleEditorSubmitPayload } from '@/components/auto-episodes/schedule-editor-dialog';
import HeadingSmall from '@/components/heading-small';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { MonitoringEventFilter, MonitoringSchedulePreset, MonitoringSettingsPageProps } from '@/types/auto-episodes';
import { AlertCircle, CalendarClock, PauseCircle, PlayCircle } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Settings',
        href: '/settings',
    },
    {
        title: 'Schedules',
        href: '/settings/schedules',
    },
];

export default function SchedulesSettings() {
    const {
        can_manage_schedules,
        is_paused,
        auto_episodes_paused_at,
        monitors,
        events,
        preset_times,
        backfill_preset_counts,
        auth,
    } = usePage<MonitoringSettingsPageProps>().props;

    const presetOptions: MonitoringSchedulePreset[] = ['hourly', 'daily', 'weekly'];
    const [selectedSeriesIds, setSelectedSeriesIds] = useState<number[]>([]);
    const [bulkPreset, setBulkPreset] = useState<MonitoringSchedulePreset>('daily');
    const [bulkApplying, setBulkApplying] = useState(false);
    const [pauseSubmitting, setPauseSubmitting] = useState(false);
    const [editorOpen, setEditorOpen] = useState(false);
    const [editorSubmitting, setEditorSubmitting] = useState(false);
    const [editingMonitor, setEditingMonitor] = useState<App.Data.AutoEpisodes.SeriesMonitorData | null>(null);
    const [eventFilter, setEventFilter] = useState<MonitoringEventFilter>('all');

    const isExternalMember = auth.user.role === 'member' && auth.user.subtype === 'external';
    const mutationLockedReason = isExternalMember
        ? 'External members can view monitoring status only. Contact your super-admin to manage schedules.'
        : 'Your account does not have permission to manage monitoring schedules.';
    const controlsDisabled = !can_manage_schedules;

    const monitorSeriesIds = useMemo(() => monitors.map((monitor) => monitor.series_id), [monitors]);
    const allSelected = monitorSeriesIds.length > 0 && selectedSeriesIds.length === monitorSeriesIds.length;
    const selectedCount = selectedSeriesIds.length;

    const filteredEvents = useMemo(() => {
        if (eventFilter === 'all') {
            return events;
        }

        return events.filter((event) => event.type === eventFilter);
    }, [eventFilter, events]);

    const editingAvailableSeasons = useMemo(() => {
        if (!editingMonitor) {
            return [];
        }

        return [...new Set(editingMonitor.monitored_seasons)].sort((a, b) => a - b);
    }, [editingMonitor]);

    const toggleSeriesSelection = (seriesId: number) => {
        setSelectedSeriesIds((current) =>
            current.includes(seriesId) ? current.filter((id) => id !== seriesId) : [...current, seriesId],
        );
    };

    const toggleSelectAll = () => {
        if (allSelected) {
            setSelectedSeriesIds([]);
            return;
        }

        setSelectedSeriesIds(monitorSeriesIds);
    };

    const applyBulkPreset = () => {
        if (controlsDisabled || selectedSeriesIds.length === 0) {
            return;
        }

        router.patch(
            route('schedules.bulk-apply'),
            {
                series_ids: selectedSeriesIds,
                preset: bulkPreset,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setBulkApplying(true),
                onSuccess: () => setSelectedSeriesIds([]),
                onFinish: () => setBulkApplying(false),
            },
        );
    };

    const toggleGlobalPause = () => {
        if (controlsDisabled) {
            return;
        }

        router.patch(
            route('schedules.pause'),
            {
                paused: !is_paused,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setPauseSubmitting(true),
                onFinish: () => setPauseSubmitting(false),
            },
        );
    };

    const openEditor = (monitor: App.Data.AutoEpisodes.SeriesMonitorData) => {
        if (controlsDisabled) {
            return;
        }

        setEditingMonitor(monitor);
        setEditorOpen(true);
    };

    const handleEditorSubmit = (payload: ScheduleEditorSubmitPayload) => {
        if (controlsDisabled || !editingMonitor) {
            return;
        }

        router.patch(route('series.monitoring.update', { model: editingMonitor.series_id }), payload.monitor, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setEditorSubmitting(true),
            onSuccess: () => setEditorOpen(false),
            onFinish: () => setEditorSubmitting(false),
        });
    };

    const eventFilters: { value: MonitoringEventFilter; label: string }[] = [
        { value: 'all', label: 'All' },
        { value: 'queued', label: 'Queued' },
        { value: 'duplicate', label: 'Duplicate' },
        { value: 'deferred', label: 'Deferred' },
        { value: 'error', label: 'Error' },
    ];

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

    const formatWeeklyDays = (days: number[]): string => {
        const labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        if (days.length === 0) {
            return 'selected days';
        }

        return days.map((day) => labels[day] ?? String(day)).join(', ');
    };

    const scheduleSummary = (monitor: App.Data.AutoEpisodes.SeriesMonitorData): string => {
        if (!monitor.enabled) {
            return 'Disabled';
        }

        if (monitor.schedule_type === 'hourly') {
            return `Hourly · ${monitor.timezone}`;
        }

        if (monitor.schedule_type === 'daily') {
            return `Daily at ${monitor.schedule_daily_time ?? 'preset'} · ${monitor.timezone}`;
        }

        return `Weekly on ${formatWeeklyDays(monitor.schedule_weekly_days)} at ${monitor.schedule_weekly_time ?? 'preset'} · ${monitor.timezone}`;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Schedules" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Monitoring management"
                        description="Manage monitor schedules, pause automation globally, and review recent auto-episode activity."
                    />

                    {controlsDisabled ? (
                        <Alert>
                            <AlertCircle className="h-4 w-4" />
                            <AlertTitle>Read-only access</AlertTitle>
                            <AlertDescription>{mutationLockedReason}</AlertDescription>
                        </Alert>
                    ) : null}

                    <Card>
                        <CardHeader>
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <CardTitle>Global automation</CardTitle>
                                    <CardDescription>
                                        Pause or resume all scheduled monitor runs for your account.
                                    </CardDescription>
                                </div>
                                <Badge variant={is_paused ? 'secondary' : 'default'}>{is_paused ? 'Paused' : 'Active'}</Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <p className="text-sm text-muted-foreground">
                                {is_paused
                                    ? `Paused since ${formatDateTime(auto_episodes_paused_at)}`
                                    : 'Automation is active. Scheduled monitor runs continue automatically.'}
                            </p>
                            <Button onClick={toggleGlobalPause} disabled={controlsDisabled || pauseSubmitting}>
                                {is_paused ? (
                                    <>
                                        <PlayCircle className="mr-2 h-4 w-4" />
                                        Resume automation
                                    </>
                                ) : (
                                    <>
                                        <PauseCircle className="mr-2 h-4 w-4" />
                                        Pause automation
                                    </>
                                )}
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Bulk schedule preset</CardTitle>
                            <CardDescription>
                                Select monitored series, then apply one preset schedule to all selected monitors.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex flex-wrap items-center gap-2 text-sm">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={toggleSelectAll}
                                    disabled={controlsDisabled || monitors.length === 0}
                                >
                                    {allSelected ? 'Clear all' : 'Select all'}
                                </Button>
                                <span className="text-muted-foreground">{selectedCount} selected</span>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                                <Select
                                    value={bulkPreset}
                                    onValueChange={(value) => setBulkPreset(value as MonitoringSchedulePreset)}
                                    disabled={controlsDisabled || bulkApplying}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select preset" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {presetOptions.map((preset) => (
                                            <SelectItem key={preset} value={preset}>
                                                {preset[0].toUpperCase() + preset.slice(1)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Button
                                    type="button"
                                    onClick={applyBulkPreset}
                                    disabled={controlsDisabled || bulkApplying || selectedSeriesIds.length === 0}
                                >
                                    Apply preset
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Monitored series</CardTitle>
                            <CardDescription>
                                Overview of monitor status, schedule, and run timing for each watchlisted series.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {monitors.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No series monitors yet. Enable monitoring from a series details page.</p>
                            ) : (
                                monitors.map((monitor) => {
                                    const selected = selectedSeriesIds.includes(monitor.series_id);

                                    return (
                                        <div
                                            key={monitor.id}
                                            className="flex flex-col gap-3 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div className="flex items-start gap-3">
                                                <Checkbox
                                                    checked={selected}
                                                    onCheckedChange={() => toggleSeriesSelection(monitor.series_id)}
                                                    disabled={controlsDisabled}
                                                />
                                                <div className="space-y-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <p className="font-medium">{monitor.series_name ?? `Series #${monitor.series_id}`}</p>
                                                        <Badge variant={monitor.enabled ? 'default' : 'secondary'}>
                                                            {monitor.enabled ? 'Enabled' : 'Disabled'}
                                                        </Badge>
                                                        {monitor.last_attempt_status ? (
                                                            <Badge variant={monitor.last_attempt_status === 'failed' ? 'destructive' : 'outline'}>
                                                                {monitor.last_attempt_status.replaceAll('_', ' ')}
                                                            </Badge>
                                                        ) : null}
                                                    </div>
                                                    <p className="text-sm text-muted-foreground">{scheduleSummary(monitor)}</p>
                                                    <div className="grid gap-1 text-xs text-muted-foreground sm:grid-cols-2 sm:gap-x-4">
                                                        <p>Last run: {formatDateTime(monitor.last_attempt_at)}</p>
                                                        <p>Next run: {formatDateTime(monitor.next_run_at)}</p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="flex flex-wrap items-center gap-2">
                                                <Button type="button" variant="outline" size="sm" asChild>
                                                    <Link href={route('series.show', { model: monitor.series_id })}>Open series</Link>
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                    size="sm"
                                                    disabled={controlsDisabled}
                                                    onClick={() => openEditor(monitor)}
                                                >
                                                    <CalendarClock className="mr-2 h-4 w-4" />
                                                    Edit schedule
                                                </Button>
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Recent activity</CardTitle>
                            <CardDescription>Filter monitor events by result type to inspect queue behavior and errors.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex flex-wrap gap-2">
                                {eventFilters.map((filterOption) => (
                                    <Button
                                        key={filterOption.value}
                                        type="button"
                                        size="sm"
                                        variant={eventFilter === filterOption.value ? 'default' : 'outline'}
                                        onClick={() => setEventFilter(filterOption.value)}
                                    >
                                        {filterOption.label}
                                    </Button>
                                ))}
                            </div>

                            {filteredEvents.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No events found for this filter.</p>
                            ) : (
                                <div className="space-y-2">
                                    {filteredEvents.map((event) => (
                                        <div key={event.id} className="rounded-md border p-3">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge variant={event.type === 'error' ? 'destructive' : 'outline'}>
                                                    {event.type}
                                                </Badge>
                                                <p className="text-sm font-medium">{event.series_name ?? `Series #${event.series_id ?? 'n/a'}`}</p>
                                                <p className="text-xs text-muted-foreground">{formatDateTime(event.created_at)}</p>
                                            </div>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {event.reason ??
                                                    (event.season && event.episode_num
                                                        ? `Season ${event.season}, Episode ${event.episode_num}`
                                                        : 'No additional details')}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <ScheduleEditorDialog
                    open={editorOpen}
                    onOpenChange={setEditorOpen}
                    mode="edit"
                    monitor={editingMonitor}
                    availableSeasons={editingAvailableSeasons}
                    presetTimes={preset_times}
                    backfillPresetCounts={backfill_preset_counts}
                    disabled={controlsDisabled}
                    disabledReason={controlsDisabled ? mutationLockedReason : undefined}
                    submitting={editorSubmitting}
                    onSubmit={handleEditorSubmit}
                />
            </SettingsLayout>
        </AppLayout>
    );
}
