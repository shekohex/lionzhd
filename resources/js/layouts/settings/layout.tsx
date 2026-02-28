import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Clock3, FolderSync, HardDriveDownload, Key, MonitorPlay, SunMoon, Tags, UserPen, Users } from 'lucide-react';
import { type PropsWithChildren } from 'react';

type SettingsNavItem = NavItem & {
    adminOnly?: boolean;
};

const sidebarNavItems: SettingsNavItem[] = [
    {
        title: 'Profile',
        url: '/settings/profile',
        icon: UserPen,
    },
    {
        title: 'Password',
        url: '/settings/password',
        icon: Key,
    },
    {
        title: 'Users',
        url: '/settings/users',
        icon: Users,
        adminOnly: true,
    },
    {
        title: 'Xtream Codes',
        url: '/settings/xtreamcodes',
        icon: MonitorPlay,
        adminOnly: true,
    },
    {
        title: 'Aria2',
        url: '/settings/aria2',
        icon: HardDriveDownload,
        adminOnly: true,
    },
    {
        title: 'Sync Media Library',
        url: '/settings/syncmedia',
        icon: FolderSync,
        adminOnly: true,
    },
    {
        title: 'Sync Categories',
        url: '/settings/synccategories',
        icon: Tags,
        adminOnly: true,
    },
    {
        title: 'Monitoring',
        url: '/settings/schedules',
        icon: Clock3,
    },
    {
        title: 'Appearance',
        url: '/settings/appearance',
        icon: SunMoon,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { auth } = usePage<SharedData>().props;

    // When server-side rendering, we only render the layout on the client...
    if (typeof window === 'undefined') {
        return null;
    }

    const isAdmin = auth.user.role === 'admin';
    const visibleSidebarItems = sidebarNavItems.filter((item) => !item.adminOnly || isAdmin);
    const currentPath = window.location.pathname;

    return (
        <div className="px-4 py-6">
            <Heading title="Settings" description="Manage your profile and system settings" />

            <div className="flex flex-col space-y-8 lg:flex-row lg:space-y-0 lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav className="flex flex-col space-y-1 space-x-0">
                        {visibleSidebarItems.map((item) => (
                            <Button
                                key={item.url}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': currentPath === item.url,
                                })}
                            >
                                <Link href={item.url} prefetch>
                                    {item.icon && <item.icon className="mr-2" />}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 md:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">{children}</section>
                </div>
            </div>
        </div>
    );
}
