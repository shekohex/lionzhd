import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import AuthLayout from '@/layouts/auth-layout';
import { Head } from '@inertiajs/react';
import { ShieldAlert, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

type ConsentScope = {
    id: string;
    label: string;
    description: string;
    allowed: boolean;
};

type Props = {
    client: { id: string; name: string };
    user: { name: string; email: string };
    scopes: ConsentScope[];
    authToken: string;
    state: string | null;
    csrfToken: string;
};

export default function Authorize({ client, user, scopes, authToken, state, csrfToken }: Props) {
    const [selectedScopes, setSelectedScopes] = useState(() =>
        scopes.filter((scope) => scope.allowed).map((scope) => scope.id),
    );
    const unavailableScopes = scopes.filter((scope) => !scope.allowed);

    const toggleScope = (scope: string, checked: boolean) => {
        setSelectedScopes((current) =>
            checked ? [...new Set([...current, scope])] : current.filter((value) => value !== scope),
        );
    };

    return (
        <AuthLayout title={`${client.name} wants to connect`} description={`Signed in as ${user.name} (${user.email})`}>
            <Head title={`Authorize ${client.name}`} />

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <ShieldCheck className="size-5" /> Choose permissions
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    <p className="text-muted-foreground text-sm">
                        Review the access this application requested. You can turn off optional permissions before
                        continuing.
                    </p>
                    {scopes.map((scope) => (
                        <label key={scope.id} className="flex gap-3 rounded-lg border p-3 text-sm">
                            <Checkbox
                                checked={selectedScopes.includes(scope.id)}
                                disabled={!scope.allowed}
                                onCheckedChange={(checked) => toggleScope(scope.id, checked === true)}
                            />
                            <span className="space-y-1">
                                <span className="block font-medium">{scope.label}</span>
                                <span className="text-muted-foreground block">{scope.description}</span>
                                <code className="text-muted-foreground text-xs">{scope.id}</code>
                            </span>
                        </label>
                    ))}

                    {unavailableScopes.length > 0 && (
                        <Alert>
                            <ShieldAlert />
                            <AlertTitle>Some permissions are unavailable</AlertTitle>
                            <AlertDescription>
                                Your account cannot grant {unavailableScopes.map((scope) => scope.label).join(', ')}.
                                They will not be included.
                            </AlertDescription>
                        </Alert>
                    )}
                </CardContent>
                <CardFooter className="flex justify-end gap-3">
                    <form method="POST" action={route('passport.authorizations.deny')}>
                        <input type="hidden" name="_token" value={csrfToken} />
                        <input type="hidden" name="_method" value="DELETE" />
                        <input type="hidden" name="client_id" value={client.id} />
                        <input type="hidden" name="state" value={state ?? ''} />
                        <input type="hidden" name="auth_token" value={authToken} />
                        <Button type="submit" variant="secondary">
                            Deny
                        </Button>
                    </form>
                    <form method="POST" action={route('passport.authorizations.approve')}>
                        <input type="hidden" name="_token" value={csrfToken} />
                        <input type="hidden" name="client_id" value={client.id} />
                        <input type="hidden" name="state" value={state ?? ''} />
                        <input type="hidden" name="auth_token" value={authToken} />
                        {selectedScopes.map((scope) => (
                            <input key={scope} type="hidden" name="scopes[]" value={scope} />
                        ))}
                        <Button type="submit">Allow access</Button>
                    </form>
                </CardFooter>
            </Card>
        </AuthLayout>
    );
}
