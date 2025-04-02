import DownloadInformation from '@/components/download-info';
import { Pagination } from '@/components/ui/pagination';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { DownloadsPageProps } from '@/types/downloads';
import { Head, usePage, usePoll } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { ErrorBoundary, FallbackProps } from 'react-error-boundary';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Downloads',
        href: '/downloads',
    },
];

const container = {
    hidden: { opacity: 0 },
    show: {
        opacity: 1,
        transition: {
            staggerChildren: 0.05,
        },
    },
};

const item = {
    hidden: { opacity: 0, y: 20 },
    show: { opacity: 1, y: 0 },
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

export default function Downloads() {
    const { props } = usePage<DownloadsPageProps>();
    usePoll(
        1000,
        {
            preserveUrl: true,
            only: ['downloads'],
        },
        {
            keepAlive: true,
            autoStart: true,
        },
    );
    const { downloads } = props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Downloads" />

            <ErrorBoundary FallbackComponent={ErrorFallback}>
                <motion.div className="flex flex-col gap-4 p-6" variants={container} initial="hidden" animate="show">
                    {downloads.data.map((download) => (
                        <motion.div key={download.id} className="rounded-md border p-4" layout variants={item}>
                            <DownloadInformation download={download} />
                        </motion.div>
                    ))}
                </motion.div>
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
