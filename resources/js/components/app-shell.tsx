import { SearchOverlay } from '@/components/search-overlay';
import { SidebarProvider } from '@/components/ui/sidebar';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';

interface AppShellProps {
    children: React.ReactNode;
    variant?: 'header' | 'sidebar';
}

export function AppShell({ children, variant = 'header' }: AppShellProps) {
    const { auth } = usePage<SharedData>().props;
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
