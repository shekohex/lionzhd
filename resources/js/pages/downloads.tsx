import DownloadInformation from '@/components/download-info';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Pagination } from '@/components/ui/pagination';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { DownloadsPageProps } from '@/types/downloads';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { CheckIcon, ChevronDownIcon, FilterIcon, FolderOpen, UsersIcon } from 'lucide-react';
import { parseAsInteger, parseAsString, useQueryState } from 'nuqs';
import { useCallback, useEffect, useMemo, useRef } from 'react';
import { ErrorBoundary, FallbackProps } from 'react-error-boundary';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Downloads',
        href: '/downloads',
    },
];

const DOWNLOADS_PATH = '/downloads';

const parseOwners = (owners: string | null): number[] => {
    if (!owners) {
        return [];
    }

    return [...new Set(
        owners
            .split(',')
            .map((ownerId) => ownerId.trim())
            .filter((ownerId) => /^\d+$/.test(ownerId))
            .map((ownerId) => Number(ownerId))
            .filter((ownerId) => ownerId > 0),
    )].sort((a, b) => a - b);
};

const stringifyOwners = (ownerIds: number[]): string | null => {
    if (ownerIds.length === 0) {
        return null;
    }

    return [...new Set(ownerIds)].sort((a, b) => a - b).join(',');
};

function ErrorFallback({ error, resetErrorBoundary }: FallbackProps) {
    return (
        <div className="flex flex-col items-center justify-center rounded-lg border border-red-300 bg-red-50 p-4 text-center text-red-800">
            <p className="text-lg font-medium">Something went wrong:</p>
            <pre className="mt-2 overflow-auto text-sm">{error.message}</pre>
            <button
                onClick={resetErrorBoundary}
                className="mt-4 rounded bg-red-600 px-4 py-2 text-white transition-colors hover:bg-red-700"
            >
                Try again
            </button>
        </div>
    );
}

const FILTER_OPTIONS = {
    downloadStatus: [
        { label: 'All', value: '' },
        { label: 'Active', value: 'active' },
        { label: 'Pending', value: 'pending' },
        { label: 'Completed', value: 'completed' },
        { label: 'Failed', value: 'failed' },
    ],
    pollingInterval: [
        { label: 'Default', value: 5000 },
        { label: '1s', value: 1000 },
        { label: '2s', value: 2000 },
        { label: '5s', value: 5000 },
    ],
};

export default function Downloads() {
    const { props } = usePage<DownloadsPageProps>();
    const [highlightedDownload, setHighlightedDownload] = useQueryState('gid', parseAsString);
    const { downloads, auth, ownerOptions = [] } = props;
    const [pollingInterval, setPollingInterval] = useQueryState('poll', parseAsInteger.withDefault(5000));
    const [downloadStatusFilter, setDownloadStatusFilter] = useQueryState('filter', parseAsString);
    const [ownersFilter, setOwnersFilter] = useQueryState('owners', parseAsString);
    const pollRef = useRef(router.poll(pollingInterval, { preserveUrl: true }, { autoStart: false }));
    const missingHighlightedGidRef = useRef<string | null>(null);
    const isAdmin = auth.user.role === 'admin';
    const isInternalMember = auth.user.role === 'member' && auth.user.subtype === 'internal';
    const isExternalMember = auth.user.role === 'member' && auth.user.subtype === 'external';
    const canOperate = isAdmin || isInternalMember;
    const readonlyReason = !canOperate
        ? isExternalMember
            ? 'Read-only: use Direct Download from movie/series pages and contact your super-admin for server access.'
            : 'Read-only: download operations are admin-only. Contact your super-admin for assistance.'
        : undefined;
    const selectedOwnerIds = useMemo(() => parseOwners(ownersFilter), [ownersFilter]);
    const selectedOwners = useMemo(
        () => ownerOptions.filter((owner) => selectedOwnerIds.includes(owner.id)),
        [ownerOptions, selectedOwnerIds],
    );
    const myDownloadsOnly = selectedOwnerIds.length === 1 && selectedOwnerIds[0] === auth.user.id;
    const summaryFrom = downloads.from ?? 0;
    const summaryTo = downloads.to ?? 0;

    const showReadOnlyToast = useCallback(() => {
        if (canOperate || !readonlyReason) {
            return;
        }

        toast.info('Downloads are read-only', {
            description: readonlyReason,
        });
    }, [canOperate, readonlyReason]);

    useEffect(() => {
        pollRef.current.start();
        return () => {
            pollRef.current.stop();
        };
    }, [pollingInterval]);

    useEffect(() => {
        if (!highlightedDownload) {
            missingHighlightedGidRef.current = null;
            return;
        }

        const highlightedDownloadExists = downloads.data.some((download) => download.gid === highlightedDownload);

        if (highlightedDownloadExists) {
            missingHighlightedGidRef.current = null;
            return;
        }

        if (missingHighlightedGidRef.current === highlightedDownload) {
            return;
        }

        missingHighlightedGidRef.current = highlightedDownload;

        toast.info('Highlighted download cleared', {
            description: 'This download is no longer accessible in your current scope.',
        });

        void setHighlightedDownload(null, { history: 'replace' });
    }, [downloads.data, highlightedDownload, setHighlightedDownload]);

    const handlePollingIntervalChange = useCallback(
        (interval: number) => {
            setPollingInterval(interval);
            // Stop the previous polling
            pollRef.current.stop();
            pollRef.current = router.poll(interval, { preserveUrl: true }, { autoStart: false });
            pollRef.current.start();
        },
        [setPollingInterval],
    );

    const setOwnerFilter = useCallback(
        (ownerIds: number[]) => {
            void setOwnersFilter(stringifyOwners(ownerIds), {
                history: 'replace',
                shallow: false,
            });
        },
        [setOwnersFilter],
    );

    const toggleOwner = useCallback(
        (ownerId: number) => {
            if (selectedOwnerIds.includes(ownerId)) {
                setOwnerFilter(selectedOwnerIds.filter((id) => id !== ownerId));
                return;
            }

            setOwnerFilter([...selectedOwnerIds, ownerId]);
        },
        [selectedOwnerIds, setOwnerFilter],
    );

    const toggleMyDownloads = useCallback(() => {
        if (myDownloadsOnly) {
            setOwnerFilter([]);
            return;
        }

        setOwnerFilter([auth.user.id]);
    }, [auth.user.id, myDownloadsOnly, setOwnerFilter]);

    const handleCancelDownload = useCallback(
        (download: App.Data.MediaDownloadRefData, deletePartial: boolean) => {
            if (!canOperate) {
                showReadOnlyToast();
                return;
            }

            router.patch(
                route('downloads.edit', { model: download.id }),
                {
                    action: 'cancel',
                    delete_partial: deletePartial,
                },
                {
                    preserveScroll: true,
                    preserveUrl: true,
                },
            );
        },
        [canOperate, showReadOnlyToast],
    );

    const handleDownloadAction = useCallback(
        (download: App.Data.MediaDownloadRefData, action: App.Enums.MediaDownloadAction) => {
            if (!canOperate) {
                showReadOnlyToast();
                return;
            }

            const payload: { action: App.Enums.MediaDownloadAction; return_to?: string } = { action };

            if (action === 'retry') {
                payload.return_to = `${DOWNLOADS_PATH}${window.location.search}`;
            }

            router.patch(
                route('downloads.edit', { model: download.id }),
                payload,
                {
                    preserveScroll: true,
                    preserveUrl: true,
                },
            );
        },
        [canOperate, showReadOnlyToast],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Downloads" />

            <ErrorBoundary FallbackComponent={ErrorFallback}>
                <div className="mt-4 flex w-full flex-wrap items-start justify-between gap-3 px-6">
                    <div className="flex flex-wrap items-center gap-2">
                        {isAdmin ? (
                            <>
                                <Button
                                    variant={myDownloadsOnly ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={toggleMyDownloads}
                                >
                                    My downloads
                                </Button>
                                <Popover>
                                    <PopoverTrigger asChild>
                                        <Button variant="outline" size="sm" className="flex items-center">
                                            <UsersIcon className="mr-2 h-4 w-4" />
                                            Owners
                                            {selectedOwnerIds.length > 0 ? ` (${selectedOwnerIds.length})` : ''}
                                            <ChevronDownIcon className="ml-2 h-3 w-3" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-80 p-0">
                                        <Command>
                                            <CommandInput placeholder="Search owners by name or email" />
                                            <CommandList>
                                                <CommandEmpty>No owners found.</CommandEmpty>
                                                <CommandGroup>
                                                    {ownerOptions.map((owner) => {
                                                        const selected = selectedOwnerIds.includes(owner.id);

                                                        return (
                                                            <CommandItem
                                                                key={owner.id}
                                                                value={`${owner.name} ${owner.email}`}
                                                                onSelect={() => toggleOwner(owner.id)}
                                                            >
                                                                <CheckIcon
                                                                    className={`h-4 w-4 ${selected ? 'opacity-100' : 'opacity-0'}`}
                                                                />
                                                                <div className="flex flex-col gap-0.5">
                                                                    <span className="text-sm">{owner.name}</span>
                                                                    <span className="text-muted-foreground text-xs">
                                                                        {owner.email}
                                                                    </span>
                                                                </div>
                                                            </CommandItem>
                                                        );
                                                    })}
                                                </CommandGroup>
                                            </CommandList>
                                        </Command>
                                    </PopoverContent>
                                </Popover>
                                {selectedOwners.map((owner) => (
                                    <button key={owner.id} type="button" onClick={() => toggleOwner(owner.id)}>
                                        <Badge variant="secondary" className="cursor-pointer px-3 py-1 text-xs">
                                            {owner.name}
                                        </Badge>
                                    </button>
                                ))}
                            </>
                        ) : null}
                    </div>

                    <div className="flex flex-wrap items-center justify-end gap-3">
                        <Popover>
                            <PopoverTrigger asChild>
                                <Button variant="outline" size="sm" className="flex items-center">
                                    <FilterIcon className="mr-2 h-4 w-4" />
                                    Download Status
                                    <ChevronDownIcon className="ml-2 h-3 w-3" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-48">
                                <div className="space-y-2">
                                    {FILTER_OPTIONS.downloadStatus.map((option) => (
                                        <Button
                                            key={option.label}
                                            variant={downloadStatusFilter === option.value ? 'default' : 'ghost'}
                                            className="w-full justify-start"
                                            onClick={() => setDownloadStatusFilter(option.value)}
                                        >
                                            {option.label}
                                        </Button>
                                    ))}
                                </div>
                            </PopoverContent>
                        </Popover>

                        <Popover>
                            <PopoverTrigger asChild>
                                <Button variant="outline" size="sm" className="flex items-center">
                                    <FilterIcon className="mr-2 h-4 w-4" />
                                    Polling Interval
                                    <ChevronDownIcon className="ml-2 h-3 w-3" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-48">
                                <div className="space-y-2">
                                    {FILTER_OPTIONS.pollingInterval.map((option) => (
                                        <Button
                                            key={option.label}
                                            variant={pollingInterval === option.value ? 'default' : 'ghost'}
                                            className="w-full justify-start"
                                            onClick={() => handlePollingIntervalChange(option.value)}
                                        >
                                            {option.label}
                                        </Button>
                                    ))}
                                </div>
                            </PopoverContent>
                        </Popover>
                    </div>
                </div>

                {!isAdmin && readonlyReason ? (
                    <div className="mx-6 mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                        {readonlyReason}
                    </div>
                ) : null}

                <div className="mx-6 mt-3 text-sm text-muted-foreground">
                    Showing {summaryFrom}-{summaryTo} of {downloads.total}
                </div>

                {downloads.data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-12 text-center">
                        <FolderOpen className="text-muted-foreground mb-4 h-12 w-12" />
                        <h3 className="mb-2 text-xl font-medium">Your downloads is empty</h3>
                        <p className="text-muted-foreground mb-6 max-w-md">
                            You can browse the series or movies and start downloading.
                        </p>
                        <div className="flex flex-row gap-2">
                            <Button asChild>
                                <Link href={route('series')}>Browse Series</Link>
                            </Button>
                            <Button asChild>
                                <Link href={route('movies')}>Browse Movies</Link>
                            </Button>
                        </div>
                    </div>
                ) : (
                    <div className="mx-6 mt-8 flex flex-col gap-4">
                        <AnimatePresence>
                            {downloads.data.map((download) => (
                                <motion.div key={download.id} className="rounded-md border p-4" layout>
                                    <DownloadInformation
                                        download={download}
                                        highlighted={download.gid === highlightedDownload}
                                        isAdminViewer={isAdmin}
                                        currentUserId={auth.user.id}
                                        isReadOnly={!canOperate}
                                        readonlyReason={readonlyReason}
                                        onReadonlyAction={showReadOnlyToast}
                                        onPause={() => {
                                            handleDownloadAction(download, 'pause');
                                        }}
                                        onResume={() => {
                                            handleDownloadAction(download, 'resume');
                                        }}
                                        onRetry={() => {
                                            handleDownloadAction(download, 'retry');
                                        }}
                                        onRemove={() => {
                                            handleDownloadAction(download, 'remove');
                                        }}
                                        onCancel={(deletePartial) => handleCancelDownload(download, deletePartial)}
                                    />
                                </motion.div>
                            ))}
                        </AnimatePresence>
                    </div>
                )}
                {downloads?.links && (
                    <div className="flex justify-center">
                        <div className="mt-8">
                            <Pagination
                                links={downloads.links}
                                preserveState={true}
                                preserveScroll={true}
                                prefetch={true}
                            />
                        </div>
                    </div>
                )}
            </ErrorBoundary>
        </AppLayout>
    );
}
