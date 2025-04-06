import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { motion } from 'framer-motion';
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';
import { useRef, useState } from 'react';

interface CastMember {
    name: string;
    role?: string;
    imageUrl?: string;
}

interface CastListProps {
    cast: string | CastMember[];
    director?: string;
    className?: string;
}

export default function CastList({ cast, director, className }: CastListProps) {
    const scrollContainerRef = useRef<HTMLDivElement>(null);
    const [canScrollLeft, setCanScrollLeft] = useState(false);
    const [canScrollRight, setCanScrollRight] = useState(true);

    // Parse cast string if needed
    const castMembers: CastMember[] = Array.isArray(cast)
        ? cast
        : cast
              ?.split(',')
              .map((name) => ({ name: name.trim() }))
              .filter((member) => member.name) || [];

    // Add director as a cast member with a role if provided
    const allMembers = director ? [{ name: director, role: 'Director' }, ...castMembers] : castMembers;

    // Check scroll position to show/hide scroll buttons
    const handleScroll = () => {
        if (!scrollContainerRef.current) return;

        const { scrollLeft, scrollWidth, clientWidth } = scrollContainerRef.current;
        setCanScrollLeft(scrollLeft > 0);
        setCanScrollRight(scrollLeft < scrollWidth - clientWidth - 10); // 10px buffer
    };

    // Scroll horizontally
    const scroll = (direction: 'left' | 'right') => {
        if (!scrollContainerRef.current) return;

        const container = scrollContainerRef.current;
        const scrollAmount = container.clientWidth * 0.8;

        container.scrollBy({
            left: direction === 'left' ? -scrollAmount : scrollAmount,
            behavior: 'smooth',
        });
    };

    if (!allMembers.length) return null;

    return (
        <div className={cn('relative w-full', className)}>
            <h2 className="mb-4 text-xl font-semibold">Cast & Crew</h2>

            {/* Scroll Container */}
            <div className="relative">
                {/* Scroll Left Button */}
                {canScrollLeft && (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="absolute top-1/2 -left-4 z-10 hidden h-10 w-10 -translate-y-1/2 rounded-full bg-black/50 backdrop-blur-sm md:flex"
                        onClick={() => scroll('left')}
                    >
                        <ChevronLeftIcon size={20} />
                    </Button>
                )}

                {/* Cast Members */}
                <div
                    ref={scrollContainerRef}
                    className="no-scrollbar flex w-full gap-4 overflow-x-auto scroll-smooth pt-1 pb-4"
                    onScroll={handleScroll}
                >
                    {allMembers.map((member, index) => (
                        <motion.div
                            key={`${member.name}-${index}`}
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.4, delay: 0.1 * (index % 10) }}
                            className="flex-shrink-0"
                        >
                            <div className="w-[140px]">
                                {/* Profile Image (placeholder if none) */}
                                <div className="bg-muted mb-2 aspect-square w-full overflow-hidden rounded-md">
                                    {member.imageUrl ? (
                                        <img
                                            src={member.imageUrl}
                                            alt={member.name}
                                            className="h-full w-full object-cover"
                                        />
                                    ) : (
                                        <div className="text-muted-foreground flex h-full w-full items-center justify-center text-2xl font-bold">
                                            {member.name.charAt(0)}
                                        </div>
                                    )}
                                </div>

                                {/* Name & Role */}
                                <div>
                                    <p className="line-clamp-1 font-medium">{member.name}</p>
                                    {member.role && (
                                        <p className="text-muted-foreground line-clamp-1 text-sm">{member.role}</p>
                                    )}
                                </div>
                            </div>
                        </motion.div>
                    ))}
                </div>

                {/* Scroll Right Button */}
                {canScrollRight && (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="absolute top-1/2 -right-4 z-10 hidden h-10 w-10 -translate-y-1/2 rounded-full bg-black/50 backdrop-blur-sm md:flex"
                        onClick={() => scroll('right')}
                    >
                        <ChevronRightIcon size={20} />
                    </Button>
                )}
            </div>
        </div>
    );
}
