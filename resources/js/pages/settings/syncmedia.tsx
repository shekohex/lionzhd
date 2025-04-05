import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Settings',
        href: '/settings',
    },
    {
        title: 'Sync Media Library',
        href: '/settings/syncmedia',
    },
];

export default function SyncMediaConfig() {
    const { patch, processing, recentlySuccessful } = useForm<never>();

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        let loadingToastId: string | number | undefined = undefined;
        patch(route('syncmedia.update'), {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                loadingToastId = toast.loading('Syncing media library...', {
                    description: 'This may take a while.',
                    duration: 10_000,
                });
            },
            onFinish: () => {
                toast.dismiss(loadingToastId);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sync Media Library" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Sync Media Library"
                        description="Synchronize your media library to update all content metadata, refresh listings, and ensure your collection is up to date. This process may take some time depending on your library size."
                    />

                    <form onSubmit={submit} className="space-y-6">
                        <div className="flex items-center gap-4">
                            <Button disabled={processing}>Sync</Button>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-neutral-600">Synced</p>
                            </Transition>
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
