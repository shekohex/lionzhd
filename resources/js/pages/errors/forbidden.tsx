import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Forbidden',
        href: '#',
    },
];

interface ForbiddenPageProps {
    reason?: string;
    message?: string;
}

export default function Forbidden({ reason = 'Forbidden', message = 'Forbidden' }: ForbiddenPageProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Forbidden" />

            <div className="mx-auto w-full max-w-2xl space-y-6 px-4 py-10">
                <div className="space-y-2">
                    <h1 className="text-3xl font-semibold tracking-tight">403</h1>
                    <p className="text-lg font-medium">{message}</p>
                </div>

                <div className="rounded-lg border p-4">
                    <p className="text-sm text-muted-foreground">Reason</p>
                    <p className="mt-2 font-medium">{reason}</p>
                </div>

                <p className="text-sm text-muted-foreground">Contact your super-admin to request access.</p>

                <div className="flex items-center gap-3">
                    <Button type="button" variant="outline" onClick={() => window.history.back()}>
                        Back
                    </Button>

                    <Button asChild>
                        <Link href={route('home')}>Home</Link>
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
