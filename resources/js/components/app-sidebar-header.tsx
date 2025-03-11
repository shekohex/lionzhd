import { Breadcrumbs } from '@/components/breadcrumbs';
import { SearchOverlay } from '@/components/search-overlay';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { SearchIcon } from 'lucide-react';
import { useState } from 'react';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const [searchOpen, setSearchOpen] = useState(false);

    return (
        <>
            <header className="border-sidebar-border/50 flex h-16 shrink-0 items-center justify-between gap-2 border-b px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
                <div className="flex items-center gap-2">
                    <SidebarTrigger className="-ml-1" />
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>

                {/* Search button */}
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => setSearchOpen(true)}
                    className="text-muted-foreground hover:text-foreground"
                    aria-label="Search"
                >
                    <SearchIcon className="h-5 w-5" />
                </Button>
            </header>

            <SearchOverlay open={searchOpen} onOpenChange={setSearchOpen} />
        </>
    );
}
