import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import useAxios from '@/hooks/use-axios';
import { AddToWatchlistResponse } from '@/types/watchlist';
import { Page } from '@inertiajs/core';
import { Bookmark, BookmarkCheck } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface WatchlistButtonProps {
    mediaId: number;
    mediaType: 'movie' | 'series';
    isInWatchlist?: boolean;
    watchlistItemId?: number;
    variant?: 'default' | 'outline' | 'ghost';
    size?: 'default' | 'sm' | 'lg' | 'icon';
}

export default function WatchlistButton({
    mediaId,
    mediaType,
    isInWatchlist = false,
    watchlistItemId,
    variant = 'outline',
    size = 'icon',
}: WatchlistButtonProps) {
    const [inWatchlist, setInWatchlist] = useState(isInWatchlist);
    const [itemId, setItemId] = useState(watchlistItemId);
    const {
        data: deleteData,
        loading: deleteLoading,
        error: deleteError,
        execute: removeFromWatchlist,
    } = useAxios(
        {
            method: 'DELETE',
        },
        false,
    );

    const {
        data: addData,
        loading: addLoading,
        error: addError,
        execute: addToWatchlist,
    } = useAxios<Page<AddToWatchlistResponse>>(
        {
            method: 'POST',
        },
        false,
    );

    const handleToggleWatchlist = async () => {
        if (inWatchlist && itemId) {
            // Remove from watchlist
            await removeFromWatchlist({
                url: route('watchlist', { id: itemId }),
            });
            console.log('Removed', deleteData);
            setInWatchlist(false);
            setItemId(undefined);
            toast.info('Removed', {
                description: 'The item has been removed from your watchlist.',
                action: {
                    label: 'Undo',
                    onClick: () => {
                        setItemId(itemId);
                        return handleToggleWatchlist();
                    },
                },
            });
        } else {
            // Add to watchlist
            await addToWatchlist({
                url: route('watchlist'),
                data: {
                    type: mediaType,
                    id: mediaId,
                },
            });
            console.log('Added', addData);
            setInWatchlist(true);
            setItemId(addData?.props.watchable_id);
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

        if (addError || deleteError) {
            console.error('Error toggling watchlist:', addError || deleteError);
            toast.error('There was an error updating your watchlist.');
        }
    };

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        variant={variant}
                        size={size}
                        onClick={handleToggleWatchlist}
                        disabled={addLoading || deleteLoading}
                        aria-label={inWatchlist ? 'Remove from watchlist' : 'Add to watchlist'}
                    >
                        {inWatchlist ? <BookmarkCheck className="h-4 w-4" /> : <Bookmark className="h-4 w-4" />}
                    </Button>
                </TooltipTrigger>
                <TooltipContent>
                    <p>{inWatchlist ? 'Remove from watchlist' : 'Add to watchlist'}</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
