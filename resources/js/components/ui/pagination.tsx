import { Button, buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { type Link as PaginationLink } from '@/types/pagination';
import { Link } from '@inertiajs/react';
import { VariantProps } from 'class-variance-authority';
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';
import { ForwardedRef, forwardRef } from 'react';

interface PaginationProps {
    links: PaginationLink[];
    className?: string;
    preserveScroll?: boolean;
    preserveState?: boolean;
    prefetch?: boolean;
    only?: string[];
}

// Polymorphic button component that can be either a regular button or an Inertia Link
const PaginationButton = forwardRef(
    (
        {
            isLink = false,
            href,
            preserveState,
            preserveScroll,
            only,
            className,
            disabled,
            variant,
            size,
            children,
            prefetch,
            ...rest
        }: React.ComponentProps<'button'> &
            VariantProps<typeof buttonVariants> & {
                asChild?: boolean;
            } & {
                isLink?: boolean;
                href?: string;
                preserveState?: boolean;
                preserveScroll?: boolean;
                prefetch?: boolean;
                only?: string[];
            },
        ref: ForwardedRef<HTMLButtonElement>,
    ) => {
        if (isLink && href) {
            return (
                <Button className={className} variant={variant} size={size} disabled={disabled} asChild {...rest}>
                    <Link
                        href={href}
                        preserveState={preserveState}
                        preserveScroll={preserveScroll}
                        prefetch={prefetch}
                        only={only}
                    >
                        {children}
                    </Link>
                </Button>
            );
        }

        return (
            <Button ref={ref} className={className} variant={variant} size={size} disabled={disabled} {...rest}>
                {children}
            </Button>
        );
    },
);

PaginationButton.displayName = 'PaginationButton';

export function Pagination({
    links,
    className,
    preserveState = true,
    preserveScroll = true,
    prefetch = false,
    only = [],
}: PaginationProps) {
    if (links.length <= 3) {
        return null;
    }

    // The links array from Laravel pagination includes "Previous" at index 0 and "Next" at the last index
    const prev = links[0];
    const next = links[links.length - 1];

    // Get the pagination links (omit first and last which are "prev" and "next")
    const pageLinks = links.slice(1, links.length - 1);

    return (
        <div className={cn('flex items-center justify-center px-4 py-3 text-center sm:px-6', className)}>
            <div className="flex w-full flex-1 items-center justify-between sm:hidden">
                {/* Mobile pagination */}
                <PaginationButton
                    isLink={!!prev.url}
                    href={prev.url || undefined}
                    preserveScroll={preserveScroll}
                    preserveState={preserveState}
                    prefetch={prefetch}
                    only={only.length ? only : undefined}
                    disabled={!prev.url}
                    variant="outline"
                    size="sm"
                    className="flex items-center gap-1"
                >
                    <ChevronLeftIcon className="h-4 w-4" />
                    Previous
                </PaginationButton>

                <PaginationButton
                    isLink={!!next.url}
                    href={next.url || undefined}
                    preserveScroll={preserveScroll}
                    preserveState={preserveState}
                    prefetch={prefetch}
                    only={only.length ? only : undefined}
                    disabled={!next.url}
                    variant="outline"
                    size="sm"
                    className="flex items-center gap-1"
                >
                    Next
                    <ChevronRightIcon className="h-4 w-4" />
                </PaginationButton>
            </div>
            <div className="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                {/* Desktop pagination */}
                <div className="flex items-center gap-2">
                    <PaginationButton
                        isLink={!!prev.url}
                        href={prev.url || undefined}
                        preserveScroll={preserveScroll}
                        preserveState={preserveState}
                        prefetch={prefetch}
                        only={only.length ? only : undefined}
                        disabled={!prev.url}
                        variant="outline"
                        size="icon"
                    >
                        <ChevronLeftIcon className="h-4 w-4" />
                        <span className="sr-only">Previous</span>
                    </PaginationButton>

                    <nav className="isolate inline-flex -space-x-px rounded-md" aria-label="Pagination">
                        {pageLinks.map((link, i) => {
                            // Handle ellipsis
                            if (link.label === '...') {
                                return (
                                    <div
                                        key={`ellipsis-${i}`}
                                        className="text-muted-foreground relative inline-flex items-center px-4 py-2 text-sm"
                                    >
                                        ...
                                    </div>
                                );
                            }

                            return (
                                <PaginationButton
                                    key={link.label}
                                    isLink={!!link.url}
                                    href={link.url || undefined}
                                    prefetch={prefetch}
                                    preserveScroll={preserveScroll}
                                    preserveState={preserveState}
                                    only={only.length ? only : undefined}
                                    disabled={!link.url}
                                    variant={link.active ? 'default' : 'outline'}
                                    size="sm"
                                    className="rounded-none first:rounded-l-md last:rounded-r-md"
                                >
                                    {link.label}
                                </PaginationButton>
                            );
                        })}
                    </nav>

                    <PaginationButton
                        isLink={!!next.url}
                        href={next.url || undefined}
                        prefetch={prefetch}
                        preserveScroll={preserveScroll}
                        preserveState={preserveState}
                        only={only.length ? only : undefined}
                        disabled={!next.url}
                        variant="outline"
                        size="icon"
                    >
                        <ChevronRightIcon className="h-4 w-4" />
                        <span className="sr-only">Next</span>
                    </PaginationButton>
                </div>
            </div>
        </div>
    );
}
