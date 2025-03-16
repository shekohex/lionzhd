import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { motion } from 'framer-motion';
import { BookmarkCheck } from 'lucide-react';
import { useState } from 'react';

interface MediaCardProps {
    title: string;
    posterUrl?: string | null;
    rating?: number | string;
    inWatchlist?: boolean;
    className?: string;
    onClick?: () => void;
}

export default function MediaCard({ title, posterUrl, rating, inWatchlist, className, onClick }: MediaCardProps) {
    const [imageError, setImageError] = useState(false);
    const hasValidImage = posterUrl && posterUrl.trim() !== '';

    // Handle image loading error
    const handleImageError = () => {
        setImageError(true);
    };

    return (
        <motion.div
            whileHover={{
                scale: 1.05,
                transition: { duration: 0.2 },
            }}
            className={cn('cursor-pointer overflow-hidden', className)}
            onClick={onClick}
        >
            <Card className="h-full overflow-hidden border-0 transition-shadow hover:shadow-lg">
                <div className="relative aspect-[2/3] overflow-hidden">
                    {hasValidImage && !imageError ? (
                        <img
                            src={posterUrl as string}
                            alt={`${title} poster`}
                            className="h-full w-full object-cover transition-opacity hover:opacity-90"
                            loading="lazy"
                            onError={handleImageError}
                        />
                    ) : (
                        <div className="bg-muted text-muted-foreground flex h-full w-full items-center justify-center p-2 text-center">
                            <span className="line-clamp-3">{title}</span>
                        </div>
                    )}

                    {rating && (
                        <div className="absolute top-2 right-2 rounded bg-black/70 px-2 py-1 text-xs font-bold text-white">
                            {typeof rating === 'number' ? rating.toFixed(1) : rating}
                        </div>
                    )}

                    {inWatchlist && inWatchlist === true && (
                        <div className="absolute top-2 left-2 rounded bg-black/70 p-2">
                            <BookmarkCheck size={20} className="text-white" />
                        </div>
                    )}
                </div>
                <CardContent className="p-3">
                    <h3 className="truncate leading-tight font-medium">{title}</h3>
                </CardContent>
            </Card>
        </motion.div>
    );
}
