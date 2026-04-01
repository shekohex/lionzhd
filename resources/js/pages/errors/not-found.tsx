import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Not Found',
        href: '#',
    },
];

interface NotFoundPageProps {
    message?: string;
}

export default function NotFound({ message = 'The page you requested could not be found.' }: NotFoundPageProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Not Found" />

            <div className="mx-auto w-full max-w-2xl space-y-6 px-4 py-10">
                <div className="space-y-2">
                    <h1 className="text-3xl font-semibold tracking-tight">404</h1>
                    <p className="text-lg font-medium">Not Found</p>
                </div>

                <div className="rounded-lg border p-4">
                    <p className="font-medium">{message}</p>
                </div>

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
