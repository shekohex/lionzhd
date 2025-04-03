import { SearchOverlay } from '@/components/search-overlay';
import { SidebarProvider } from '@/components/ui/sidebar';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

interface AppShellProps {
    children: React.ReactNode;
    variant?: 'header' | 'sidebar';
}

export function AppShell({ children, variant = 'header' }: AppShellProps) {
    const { auth, errors: rawErrors } = usePage<SharedData>().props;
    const errors = useMemo(() => rawErrors, [rawErrors]);
    const isAuthenticated = auth.user !== null;

    // Sidebar state
    const [isOpen, setIsOpen] = useState(() =>
        typeof window !== 'undefined' ? localStorage.getItem('sidebar') !== 'false' : true,
    );

    // Search overlay state
    const [searchOpen, setSearchOpen] = useState(false);

    const handleSidebarChange = (open: boolean) => {
        setIsOpen(open);
        if (typeof window !== 'undefined') {
            localStorage.setItem('sidebar', String(open));
        }
    };

    // Handle errors
    useEffect(() => {
        if (errors && Object.keys(errors).length > 0) {
            console.error('Errors:', errors);
            for (const [key, value] of Object.entries(errors)) {
                toast.error(value as string, {
                    description: `Error in ${key}: ${value}`,
                    duration: 5000,
                    action: {
                        label: 'Dismiss',
                        onClick: () => {
                            toast.dismiss();
                        },
                    },
                });
            }
        }
    }, [errors]);

    if (variant === 'header') {
        return (
            <div className="flex min-h-screen w-full flex-col">
                {children}

                {/* SearchOverlay for authenticated users */}
                {isAuthenticated && <SearchOverlay open={searchOpen} onOpenChange={setSearchOpen} />}
            </div>
        );
    }

    return (
        <SidebarProvider defaultOpen={isOpen} open={isOpen} onOpenChange={handleSidebarChange}>
            {children}

            {/* SearchOverlay for authenticated users */}
            {isAuthenticated && <SearchOverlay open={searchOpen} onOpenChange={setSearchOpen} />}
        </SidebarProvider>
    );
}
