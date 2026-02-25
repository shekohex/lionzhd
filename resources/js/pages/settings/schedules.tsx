import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

import HeadingSmall from '@/components/heading-small';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

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
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Schedules" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Auto-download schedules" description="Coming in Phase 7." />
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
