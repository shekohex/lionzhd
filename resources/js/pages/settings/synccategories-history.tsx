import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type Pagination from '@/types/pagination';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Settings',
        href: '/settings',
    },
    {
        title: 'Sync Categories',
        href: '/settings/synccategories',
    },
    {
        title: 'History',
        href: '/settings/synccategories/history',
    },
];

type CategorySyncSummary = {
    created?: number;
    updated?: number;
    removed?: number;
    moved_to_uncategorized_vod?: number;
    moved_to_uncategorized_series?: number;
    remapped_from_uncategorized_vod?: number;
    remapped_from_uncategorized_series?: number;
};

type CategorySyncRun = {
    id: number;
    status: 'running' | 'success' | 'success_with_warnings' | 'failed';
    started_at: string | null;
    finished_at: string | null;
    summary: CategorySyncSummary;
    top_issues: string[];
    requested_by: {
        id: number;
        name: string;
    } | null;
};

interface SyncCategoriesHistoryPageProps extends SharedData {
    runs: Pagination<CategorySyncRun>;
}

const formatDateTime = (value: string | null) => {
    if (value === null) {
        return '—';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return parsed.toLocaleString();
};

const toCount = (value?: number) => {
    if (typeof value !== 'number' || Number.isNaN(value)) {
        return 0;
    }

    return value;
};

const statusBadgeVariant = (status: CategorySyncRun['status']): 'default' | 'secondary' | 'destructive' | 'outline' => {
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

const statusLabel = (status: CategorySyncRun['status']) => status.replaceAll('_', ' ');

export default function SyncCategoriesHistoryPage() {
    const { runs } = usePage<SyncCategoriesHistoryPageProps>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sync Categories History" />

            <SettingsLayout>
                <div className="space-y-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <HeadingSmall
                            title="Sync Categories History"
                            description="Review recent category sync runs with summary counts and top issues."
                        />

                        <Button variant="outline" asChild>
                            <Link href={route('synccategories.edit')}>Back to Sync Categories</Link>
                        </Button>
                    </div>

                    <div className="space-y-3">
                        {runs.data.length === 0 && (
                            <div className="rounded-lg border p-4 text-sm text-muted-foreground">No category sync runs yet.</div>
                        )}

                        {runs.data.map((run) => (
                            <div key={run.id} className="space-y-3 rounded-lg border p-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="space-y-1">
                                        <p className="text-sm font-medium">Run #{run.id}</p>
                                        <p className="text-xs text-muted-foreground">
                                            Started: {formatDateTime(run.started_at)}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Finished: {formatDateTime(run.finished_at)}
                                        </p>
                                        {run.requested_by !== null && (
                                            <p className="text-xs text-muted-foreground">Requested by: {run.requested_by.name}</p>
                                        )}
                                    </div>

                                    <Badge variant={statusBadgeVariant(run.status)} className="capitalize">
                                        {statusLabel(run.status)}
                                    </Badge>
                                </div>

                                <div className="grid grid-cols-2 gap-2 text-xs text-muted-foreground md:grid-cols-4">
                                    <p>Created: {toCount(run.summary.created)}</p>
                                    <p>Updated: {toCount(run.summary.updated)}</p>
                                    <p>Removed: {toCount(run.summary.removed)}</p>
                                    <p>Moved VOD: {toCount(run.summary.moved_to_uncategorized_vod)}</p>
                                    <p>Moved Series: {toCount(run.summary.moved_to_uncategorized_series)}</p>
                                    <p>Remapped VOD: {toCount(run.summary.remapped_from_uncategorized_vod)}</p>
                                    <p>Remapped Series: {toCount(run.summary.remapped_from_uncategorized_series)}</p>
                                </div>

                                <div className="space-y-1">
                                    <p className="text-xs font-medium uppercase text-muted-foreground">Top issues</p>

                                    {run.top_issues.length === 0 && (
                                        <p className="text-sm text-muted-foreground">None</p>
                                    )}

                                    {run.top_issues.length > 0 && (
                                        <ul className="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                                            {run.top_issues.map((issue, index) => (
                                                <li key={`${run.id}-${index}`}>{issue}</li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                        <p>
                            Page {runs.current_page} of {runs.last_page}
                        </p>

                        <div className="flex items-center gap-2">
                            {runs.prev_page_url ? (
                                <Button size="sm" variant="outline" asChild>
                                    <Link href={runs.prev_page_url}>Previous</Link>
                                </Button>
                            ) : null}

                            {runs.next_page_url ? (
                                <Button size="sm" variant="outline" asChild>
                                    <Link href={runs.next_page_url}>Next</Link>
                                </Button>
                            ) : null}
                        </div>
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
