import { type BreadcrumbItem, type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'XtreamCodes settings',
        href: '/settings/xtreamcodes',
    },
];

interface XtreamCodesConfigPageProps extends SharedData {
    host: string;
    port: number;
    username: string;
    password: string;
}

interface XtreamCodesForm {
    host: string;
    port: number;
    username: string;
    password: string;
}

export default function XtreamCodesConfig() {
    const { props } = usePage<XtreamCodesConfigPageProps>();

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm<Required<XtreamCodesForm>>({
        host: props.host,
        port: props.port,
        username: props.username,
        password: props.password,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('xtreamcodes.update'), {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="XtreamCodes Settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Xtream Codes Settings" description="Update XtreamCodes configurations" />

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
                            <Label htmlFor="username">Username</Label>

                            <Input
                                id="username"
                                className="mt-1 block w-full"
                                value={data.username}
                                onChange={(e) => setData('username', e.target.value)}
                                required
                                autoComplete="username"
                                placeholder="Username"
                            />

                            <InputError className="mt-2" message={errors.username} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">Password</Label>

                            <Input
                                id="password"
                                type="password"
                                className="mt-1 block w-full"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                required
                                autoComplete="new-password"
                                placeholder="Password"
                            />

                            <InputError className="mt-2" message={errors.password} />
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
