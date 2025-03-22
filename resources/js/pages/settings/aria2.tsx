import { type BreadcrumbItem, type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Aria2 settings',
        href: '/settings/aria2',
    },
];

interface Aria2ConfigPageProps extends SharedData, App.Data.Aria2ConfigData {}

export default function Aria2Config() {
    const { props } = usePage<Aria2ConfigPageProps>();

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm<App.Data.Aria2ConfigData>(props);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('aria2.update'), {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Aria2 Settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Aria2 Settings" description="Update Aria2 configurations" />

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="host">Host</Label>

                            <Input
                                id="host"
                                className="mt-1 block w-full"
                                value={data.host}
                                onChange={(e) => setData('host', e.target.value)}
                                required
                                autoComplete="off"
                                placeholder="Xtream Codes Host"
                            />

                            <InputError className="mt-2" message={errors.host} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="port">Port</Label>

                            <Input
                                id="port"
                                type="number"
                                className="mt-1 block w-full"
                                value={data.port}
                                onChange={(e) => setData('port', Number(e.target.value))}
                                required
                                autoComplete="off"
                                placeholder="Port number"
                            />

                            <InputError className="mt-2" message={errors.port} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="secret">Secret</Label>

                            <Input
                                id="secret"
                                type="password"
                                className="mt-1 block w-full"
                                value={data.secret}
                                onChange={(e) => setData('secret', e.target.value)}
                                required
                                autoComplete="new-password"
                                placeholder="Secret key"
                            />

                            <InputError className="mt-2" message={errors.secret} />
                        </div>

                        <div className="flex items-center justify-between">
                            <Label htmlFor="use_ssl" className="text-sm font-medium">
                                Use SSL
                            </Label>
                            <Checkbox
                                id="use_ssl"
                                checked={data.use_ssl}
                                onCheckedChange={(checked) => setData('use_ssl', checked as boolean)}
                            />
                        </div>

                        <div className="flex items-center gap-4">
                            <Button disabled={processing}>Save</Button>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-neutral-600">Saved</p>
                            </Transition>
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
