import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Copy, Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

type Token = {
    id: number;
    name: string;
    abilities: string[];
    last_used_at: string | null;
    created_at: string;
};

type AbilityOption = {
    value: string;
    label: string;
    description: string;
};

type TokensPageProps = SharedData & {
    tokens: Token[];
    abilityOptions: AbilityOption[];
    flash: SharedData['flash'] & { api_token?: string | null };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings' },
    { title: 'API tokens', href: '/settings/tokens' },
];

export default function Tokens({ tokens, abilityOptions }: { tokens: Token[]; abilityOptions: AbilityOption[] }) {
    const { flash } = usePage<TokensPageProps>().props;
    const { data, setData, post, processing, errors, reset } = useForm<{ name: string; abilities: string[] }>({
        name: '',
        abilities: ['read'],
    });

    const toggleAbility = (ability: string, checked: boolean) => {
        setData(
            'abilities',
            checked
                ? Array.from(new Set([...data.abilities, ability]))
                : data.abilities.filter((value) => value !== ability),
        );
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('tokens.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const copyToken = async () => {
        if (flash.api_token) {
            await navigator.clipboard.writeText(flash.api_token);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="API tokens" />

            <SettingsLayout>
                <div className="space-y-8">
                    <HeadingSmall
                        title="API tokens"
                        description="Create scoped tokens for external API clients. New token secrets are shown once."
                    />

                    {flash.api_token && (
                        <div className="space-y-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100">
                            <p className="font-medium">Copy this token now. It will not be shown again.</p>
                            <div className="flex gap-2">
                                <Input readOnly value={flash.api_token} className="font-mono text-xs" />
                                <Button type="button" variant="secondary" onClick={copyToken}>
                                    <Copy className="mr-2 size-4" /> Copy
                                </Button>
                            </div>
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-5 rounded-lg border p-4">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Token name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                placeholder="Automation client"
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="space-y-3">
                            <Label>Abilities</Label>
                            {abilityOptions.map((option) => (
                                <label key={option.value} className="flex gap-3 rounded-md border p-3 text-sm">
                                    <Checkbox
                                        checked={data.abilities.includes(option.value)}
                                        onCheckedChange={(checked) => toggleAbility(option.value, checked === true)}
                                    />
                                    <span className="space-y-1">
                                        <span className="block font-medium">{option.label}</span>
                                        <span className="text-muted-foreground block">{option.description}</span>
                                    </span>
                                </label>
                            ))}
                            <InputError message={errors.abilities} />
                        </div>

                        <Button disabled={processing}>Create token</Button>
                    </form>

                    <details className="rounded-lg border p-4 text-sm">
                        <summary className="cursor-pointer font-medium">How to use an API token</summary>
                        <pre className="bg-muted mt-3 overflow-x-auto rounded-md p-3 text-xs">
                            <code>{'Authorization: Bearer <token>\nAccept: application/vnd.api+json'}</code>
                        </pre>
                    </details>

                    <div className="space-y-4">
                        <HeadingSmall title="Existing tokens" description="Revoke tokens that are no longer used." />
                        {tokens.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No API tokens created yet.</p>
                        ) : (
                            tokens.map((token) => (
                                <div
                                    key={token.id}
                                    className="flex items-start justify-between gap-4 rounded-lg border p-4"
                                >
                                    <div className="space-y-2">
                                        <div className="font-medium">{token.name}</div>
                                        <div className="text-muted-foreground text-xs">
                                            Created {new Date(token.created_at).toLocaleString()}
                                            {token.last_used_at
                                                ? ` • Last used ${new Date(token.last_used_at).toLocaleString()}`
                                                : ' • Never used'}
                                        </div>
                                        <div className="flex flex-wrap gap-1">
                                            {token.abilities.map((ability) => (
                                                <span key={ability} className="bg-muted rounded px-2 py-1 text-xs">
                                                    {ability}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => {
                                            if (window.confirm(`Revoke token "${token.name}"?`)) {
                                                router.delete(route('tokens.destroy', token.id), {
                                                    preserveScroll: true,
                                                });
                                            }
                                        }}
                                    >
                                        <Trash2 className="mr-2 size-4" /> Revoke
                                    </Button>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
