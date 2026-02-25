import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Settings',
        href: '/settings',
    },
    {
        title: 'Users',
        href: '/settings/users',
    },
];

type UserItem = {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'member';
    subtype: 'internal' | 'external';
    is_super_admin: boolean;
};

interface UsersPageProps extends SharedData {
    users: UserItem[];
    can_manage_admin_roles: boolean;
}

export default function UsersSettings() {
    const { users, can_manage_admin_roles } = usePage<UsersPageProps>().props;

    const patchWithConfirmation = (
        message: string,
        routeName: string,
        routeParams: Record<string, number>,
        data: Record<string, string>,
    ) => {
        if (!window.confirm(message)) {
            return;
        }

        router.patch(route(routeName, routeParams), data, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const updateSubtype = (user: UserItem) => {
        const nextSubtype = user.subtype === 'internal' ? 'external' : 'internal';

        patchWithConfirmation(
            `Change ${user.name} subtype to ${nextSubtype}?`,
            'users.subtype.update',
            { user: user.id },
            { subtype: nextSubtype },
        );
    };

    const updateRole = (user: UserItem, role: 'admin' | 'member') => {
        const action = role === 'admin' ? 'promote' : 'demote';

        patchWithConfirmation(
            `Are you sure you want to ${action} ${user.name}?`,
            'users.role.update',
            { user: user.id },
            { role },
        );
    };

    const transferSuperAdmin = (user: UserItem) => {
        patchWithConfirmation(
            `Transfer super-admin to ${user.name}?`,
            'users.super-admin.transfer',
            { user: user.id },
            {},
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Users"
                        description="Manage member subtype and admin role assignments with confirmation."
                    />

                    <div className="space-y-3">
                        {users.map((user) => (
                            <div key={user.id} className="space-y-3 rounded-lg border p-4">
                                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                    <div className="space-y-1">
                                        <p className="font-medium">{user.name}</p>
                                        <p className="text-sm text-muted-foreground">{user.email}</p>
                                        <div className="flex flex-wrap items-center gap-2 pt-1">
                                            <Badge variant="outline">
                                                {user.role === 'admin' ? 'Admin' : 'Member'}
                                            </Badge>

                                            <Badge variant="secondary">
                                                {user.role === 'member'
                                                    ? user.subtype === 'internal'
                                                        ? 'Internal'
                                                        : 'External'
                                                    : 'N/A'}
                                            </Badge>

                                            {user.is_super_admin && <Badge>Super-admin</Badge>}
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-2">
                                        {user.role === 'member' && (
                                            <Button size="sm" variant="outline" onClick={() => updateSubtype(user)}>
                                                Set {user.subtype === 'internal' ? 'External' : 'Internal'}
                                            </Button>
                                        )}

                                        {can_manage_admin_roles && user.role === 'member' && (
                                            <Button size="sm" onClick={() => updateRole(user, 'admin')}>
                                                Promote to Admin
                                            </Button>
                                        )}

                                        {can_manage_admin_roles && user.role === 'admin' && !user.is_super_admin && (
                                            <>
                                                <Button size="sm" variant="outline" onClick={() => transferSuperAdmin(user)}>
                                                    Make Super-admin
                                                </Button>

                                                <Button size="sm" variant="destructive" onClick={() => updateRole(user, 'member')}>
                                                    Demote to Member
                                                </Button>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
