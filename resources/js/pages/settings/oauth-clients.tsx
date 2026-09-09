import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Copy, RefreshCw, Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

type OAuthClient = {
    id: string;
    name: string;
    redirect_uri: string;
    confidential: boolean;
    created_at: string;
};

type ScopeOption = {
    value: string;
    label: string;
    description: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings' },
    { title: 'OAuth clients', href: '/settings/oauth-clients' },
];

function ClientCard({ client }: { client: OAuthClient }) {
    const { data, setData, patch, processing, errors } = useForm({
        name: client.name,
        redirect_uri: client.redirect_uri,
    });

    const update: FormEventHandler = (event) => {
        event.preventDefault();
        patch(route('oauth-clients.update', client.id), { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>{client.name}</CardTitle>
                <CardDescription>Created {new Date(client.created_at).toLocaleString()}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="space-y-2">
                    <Label>Client ID</Label>
                    <div className="flex gap-2">
                        <Input readOnly value={client.id} className="font-mono text-xs" />
                        <Button
                            type="button"
                            variant="secondary"
                            size="icon"
                            onClick={() => navigator.clipboard.writeText(client.id)}
                        >
                            <Copy className="size-4" />
                            <span className="sr-only">Copy client ID</span>
                        </Button>
                    </div>
                </div>

                <details className="rounded-lg border p-4">
                    <summary className="cursor-pointer text-sm font-medium">Edit client</summary>
                    <form onSubmit={update} className="mt-4 space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor={`name-${client.id}`}>Client name</Label>
                            <Input
                                id={`name-${client.id}`}
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`redirect-${client.id}`}>Callback URL</Label>
                            <Input
                                id={`redirect-${client.id}`}
                                type="url"
                                value={data.redirect_uri}
                                onChange={(event) => setData('redirect_uri', event.target.value)}
                            />
                            <InputError message={errors.redirect_uri} />
                        </div>
                        <Button disabled={processing}>Save changes</Button>
                    </form>
                </details>
            </CardContent>
            <CardFooter className="flex flex-wrap gap-2">
                {client.confidential ? (
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        onClick={() => {
                            if (
                                window.confirm(
                                    `Generate a new secret for "${client.name}"? The current secret will stop working.`,
                                )
                            ) {
                                router.post(route('oauth-clients.secret', client.id), {}, { preserveScroll: true });
                            }
                        }}
                    >
                        <RefreshCw className="mr-2 size-4" /> Rotate secret
                    </Button>
                ) : (
                    <Badge variant="secondary">Public PKCE client</Badge>
                )}
                <Button
                    type="button"
                    variant="destructive"
                    size="sm"
                    onClick={() => {
                        if (window.confirm(`Revoke OAuth client "${client.name}" and all of its tokens?`)) {
                            router.delete(route('oauth-clients.destroy', client.id), { preserveScroll: true });
                        }
                    }}
                >
                    <Trash2 className="mr-2 size-4" /> Revoke
                </Button>
            </CardFooter>
        </Card>
    );
}

export default function OAuthClients({
    clients,
    scopeOptions,
}: {
    clients: OAuthClient[];
    scopeOptions: ScopeOption[];
}) {
    const { flash } = usePage<SharedData>().props;
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', redirect_uri: '' });

    const create: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('oauth-clients.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="OAuth clients" />
            <SettingsLayout>
                <div className="space-y-8">
                    <HeadingSmall
                        title="OAuth clients"
                        description="Register external services that connect to Lionz using the OAuth 2.0 authorization code flow."
                    />

                    {flash.oauth_client && (
                        <div className="space-y-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100">
                            <p className="font-medium">Copy this client secret now. It will not be shown again.</p>
                            <div className="grid gap-2">
                                <Label>Client ID</Label>
                                <div className="flex gap-2">
                                    <Input readOnly value={flash.oauth_client.id} className="font-mono text-xs" />
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => navigator.clipboard.writeText(flash.oauth_client!.id)}
                                    >
                                        <Copy className="mr-2 size-4" /> Copy
                                    </Button>
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label>Client secret</Label>
                                <div className="flex gap-2">
                                    <Input readOnly value={flash.oauth_client.secret} className="font-mono text-xs" />
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => navigator.clipboard.writeText(flash.oauth_client!.secret)}
                                    >
                                        <Copy className="mr-2 size-4" /> Copy
                                    </Button>
                                </div>
                            </div>
                        </div>
                    )}

                    <form onSubmit={create} className="space-y-5 rounded-lg border p-4">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Client name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                placeholder="Home Assistant"
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="redirect_uri">Callback URL</Label>
                            <Input
                                id="redirect_uri"
                                type="url"
                                value={data.redirect_uri}
                                onChange={(event) => setData('redirect_uri', event.target.value)}
                                placeholder="https://service.example.com/oauth/callback"
                            />
                            <InputError message={errors.redirect_uri} />
                        </div>
                        <Button disabled={processing}>Create OAuth client</Button>
                    </form>

                    <details className="rounded-lg border p-4 text-sm">
                        <summary className="cursor-pointer font-medium">How to add and connect an OAuth client</summary>
                        <div className="text-muted-foreground mt-4 space-y-3">
                            <p>
                                Open Settings, choose OAuth Clients, enter the external service name and its exact
                                callback URL, then select Create OAuth client.
                            </p>
                            <p>
                                Copy the client ID and one-time client secret into the external service. The service
                                should use <code className="text-foreground">/oauth/authorize</code> as the
                                authorization endpoint and <code className="text-foreground">/oauth/token</code> as the
                                token endpoint.
                            </p>
                            <p>
                                Users review and can reduce the requested scopes on the consent screen before approving
                                access.
                            </p>
                            <div className="flex flex-wrap gap-2">
                                {scopeOptions.map((scope) => (
                                    <Badge key={scope.value} variant="outline">
                                        {scope.value}
                                    </Badge>
                                ))}
                            </div>
                        </div>
                    </details>

                    <div className="space-y-4">
                        <HeadingSmall
                            title="Registered clients"
                            description="Update callback URLs, rotate secrets, or revoke clients and their tokens."
                        />
                        {clients.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No OAuth clients registered yet.</p>
                        ) : (
                            clients.map((client) => <ClientCard key={client.id} client={client} />)
                        )}
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
