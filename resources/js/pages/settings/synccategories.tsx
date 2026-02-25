import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Settings',
        href: '/settings',
    },
    {
        title: 'Sync Categories',
        href: '/settings/synccategories',
    },
];

type SyncCategoriesFormData = {
    forceEmptyVod: boolean;
    forceEmptySeries: boolean;
};

export default function SyncCategoriesPage() {
    const { patch, processing, recentlySuccessful, errors, transform } = useForm<SyncCategoriesFormData>({
        forceEmptyVod: false,
        forceEmptySeries: false,
    });
    const extendedErrors = errors as Record<string, string | undefined>;

    const submit = (forceEmptyVod: boolean, forceEmptySeries: boolean) => {
        let loadingToastId: string | number | undefined;

        transform(() => ({
            forceEmptyVod,
            forceEmptySeries,
        }));

        patch(route('synccategories.update'), {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                loadingToastId = toast.loading('Syncing categories...', {
                    description: 'This may take a while.',
                    duration: 10_000,
                });
            },
            onError: (formErrors) => {
                const requiresVodConfirmation = Boolean(formErrors.forceEmptyVod);
                const requiresSeriesConfirmation = Boolean(formErrors.forceEmptySeries);

                if (!requiresVodConfirmation && !requiresSeriesConfirmation) {
                    return;
                }

                const sources = [
                    requiresVodConfirmation ? 'VOD' : null,
                    requiresSeriesConfirmation ? 'Series' : null,
                ].filter((source): source is string => source !== null);

                const confirmed = window.confirm(
                    `Xtream returned zero categories for ${sources.join(' and ')}. Queue sync with explicit force flags?`,
                );

                if (!confirmed) {
                    return;
                }

                submit(requiresVodConfirmation, requiresSeriesConfirmation);
            },
            onFinish: () => {
                toast.dismiss(loadingToastId);
            },
        });
    };

    const onSubmit: FormEventHandler = (event) => {
        event.preventDefault();

        submit(false, false);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sync Categories" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Sync Categories"
                        description="Queue a combined categories sync for VOD and Series. If a source returns zero categories, explicit confirmation is required before a destructive apply is queued."
                    />

                    <form onSubmit={onSubmit} className="space-y-4">
                        <div className="flex flex-wrap items-center gap-3">
                            <Button disabled={processing}>Sync</Button>

                            <Button type="button" variant="outline" asChild>
                                <Link href={route('synccategories.history')}>View History</Link>
                            </Button>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-neutral-600">Queued</p>
                            </Transition>
                        </div>

                        <InputError message={extendedErrors.confirmation} />
                        <InputError message={extendedErrors.preflight} />
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
