import { SearchInput } from '@/components/search-input';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { useEffect } from 'react';

interface SearchOverlayProps {
    open: boolean;
    enabled?: boolean;
    onOpenChange: (open: boolean) => void;
}

export function SearchOverlay({ open, onOpenChange, enabled = false }: SearchOverlayProps) {
    // Handle keyboard shortcuts
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            // Close on escape
            if (e.key === 'Escape' && open) {
                onOpenChange(false);
            }

            // Open on Cmd+K / Ctrl+K
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                onOpenChange(true);
            }
        };

        if (!enabled) {
            return;
        }
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [open, onOpenChange, enabled]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-3xl gap-0 p-0" aria-description="Search for movies, series, and more">
                <div className="w-full p-4">
                    <SearchInput searchRoute="search.lightweight" fullWidth autoFocus className="shadow-none" />
                </div>
            </DialogContent>
        </Dialog>
    );
}
