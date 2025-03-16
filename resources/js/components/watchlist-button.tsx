import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Bookmark, BookmarkCheck } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface WatchlistButtonProps {
    isInWatchlist?: () => boolean;
    variant?: 'default' | 'outline' | 'ghost';
    size?: 'default' | 'sm' | 'lg' | 'icon';
    onAddToWatchlist?: () => void;
    onRemoveFromWatchlist?: () => void;
}

export default function WatchlistButton({
    isInWatchlist,
    variant = 'outline',
    size = 'icon',
    onAddToWatchlist,
    onRemoveFromWatchlist,
}: WatchlistButtonProps) {
    const [loading, setLoading] = useState(false);
    const handleToggleWatchlist = async () => {
        setLoading(true);
        if (isInWatchlist?.()) {
            // Remove from watchlist
            if (onRemoveFromWatchlist?.()) {
                toast.info('Removed', {
                    description: 'The item has been removed from your watchlist.',
                });
            }
        } else {
            // Add to watchlist
            if (onAddToWatchlist?.()) {
                toast('Added to watchlist', {
                    description: 'The item has been added to your watchlist.',
                    action: {
                        label: 'Undo',
                        onClick: () => {
                            return handleToggleWatchlist();
                        },
                    },
                });
            }
        }
        setLoading(false);
    };

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        variant={variant}
                        size={size}
                        onClick={handleToggleWatchlist}
                        disabled={loading}
                        aria-label={isInWatchlist?.() ? 'Remove from watchlist' : 'Add to watchlist'}
                    >
                        {isInWatchlist?.() ? <BookmarkCheck className="h-4 w-4" /> : <Bookmark className="h-4 w-4" />}
                    </Button>
                </TooltipTrigger>
                <TooltipContent>
                    <p>{isInWatchlist?.() ? 'Remove from watchlist' : 'Add to watchlist'}</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
