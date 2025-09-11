import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { motion } from 'framer-motion';
import { X } from 'lucide-react';
import { useEffect, useState } from 'react';

interface VideoTrailerPreviewProps {
    trailerUrl: string;
    isOpen: boolean;
    onClose: () => void;
    className?: string;
}

export default function VideoTrailerPreview({ trailerUrl, isOpen, onClose, className }: VideoTrailerPreviewProps) {
    const [videoId, setVideoId] = useState<string | null>(null);

    // Extract YouTube video ID from URL
    useEffect(() => {
        if (!trailerUrl) return;

        // Handle different YouTube URL formats
        let id = null;

        // Regular YouTube URLs
        const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=)([^#&?]*).*/;
        const match = trailerUrl.match(regExp);

        if (match && match[2].length === 11) {
            id = match[2];
        } else {
            // Try extracting directly if it's just an ID
            if (trailerUrl.length === 11) {
                id = trailerUrl;
            }
        }

        setVideoId(id);
    }, [trailerUrl]);

    // Don't render if no video ID or not open
    if (!videoId || !isOpen) return null;

    return (
        <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className={cn('fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4', className)}
        >
            <div className="relative w-full max-w-5xl">
                {/* Close button */}
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={onClose}
                    className="bg-background/90 absolute right-2 top-2 z-10 rounded-full p-2 backdrop-blur-sm md:-top-4 md:-right-4"
                >
                    <X className="h-6 w-6" />
                </Button>

                {/* YouTube iframe */}
                <div className="relative overflow-hidden rounded-lg pb-[56.25%]">
                    <iframe
                        src={`https://www.youtube.com/embed/${videoId}?autoplay=1&controls=1&rel=0`}
                        title="YouTube video player"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowFullScreen
                        className="absolute top-0 left-0 h-full w-full"
                    />
                </div>
            </div>
        </motion.div>
    );
}
