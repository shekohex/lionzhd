import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { type SeasonsWithEpisodes } from '@/types/series';
import { motion } from 'framer-motion';
import { DownloadIcon } from 'lucide-react';
import { useState } from 'react';

interface EpisodeListProps {
    seasonsWithEpisodes: SeasonsWithEpisodes;
    className?: string;
    onDownloadEpisode?: (index: number, episode: App.Http.Integrations.LionzTv.Responses.Episode) => void;
}

export default function EpisodeList({ seasonsWithEpisodes, className, onDownloadEpisode }: EpisodeListProps) {
    // Get available season numbers and sort them
    const seasonNumbers = Object.keys(seasonsWithEpisodes)
        .map(Number)
        .sort((a, b) => a - b);

    const [selectedSeason, setSelectedSeason] = useState<number>(seasonNumbers.length > 0 ? seasonNumbers[0] : 0);

    // Get episodes for the selected season
    const episodes = selectedSeason ? seasonsWithEpisodes[selectedSeason] || [] : [];

    if (seasonNumbers.length === 0) return null;

    return (
        <div className={cn('relative w-full', className)}>
            <div className="mb-6 flex items-center justify-between">
                <h2 className="text-xl font-semibold">Episodes</h2>

                {/* Season selector */}
                {seasonNumbers.length > 1 && (
                    <Select value={String(selectedSeason)} onValueChange={(value) => setSelectedSeason(Number(value))}>
                        <SelectTrigger className="w-[180px]">
                            <SelectValue placeholder="Select season" />
                        </SelectTrigger>
                        <SelectContent>
                            {seasonNumbers.map((season) => (
                                <SelectItem key={season} value={String(season)}>
                                    Season {season}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}
            </div>

            {/* Episodes list */}
            <div className="space-y-4">
                {episodes.map((episode, index) => (
                    <motion.div
                        key={episode.id}
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.4, delay: 0.05 * (index % 20) }}
                    >
                        <EpisodeCard episode={episode} onDownload={() => onDownloadEpisode?.(index, episode)} />
                    </motion.div>
                ))}

                {episodes.length === 0 && (
                    <div className="flex h-32 items-center justify-center rounded-lg border border-dashed">
                        <p className="text-muted-foreground text-sm">No episodes available for this season</p>
                    </div>
                )}
            </div>
        </div>
    );
}

interface EpisodeCardProps {
    episode: App.Http.Integrations.LionzTv.Responses.Episode;
    onDownload?: () => void;
}

function EpisodeCard({ episode, onDownload }: EpisodeCardProps) {
    const [isHovered, setIsHovered] = useState(false);

    // Format episode number with leading zero if needed
    const formattedEpisodeNum = episode.episodeNum < 10 ? `0${episode.episodeNum}` : String(episode.episodeNum);

    return (
        <div
            className="group hover:bg-accent/30 relative flex w-full gap-4 rounded-md border p-4 transition-all"
            onMouseEnter={() => setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
        >
            {/* Episode number */}
            <div className="flex w-12 flex-shrink-0 items-center justify-center">
                <span className="text-muted-foreground text-2xl font-semibold group-hover:opacity-0">
                    {formattedEpisodeNum}
                </span>

                {/* Play button (shown on hover) */}
                <Button
                    variant="ghost"
                    size="icon"
                    className="absolute left-4 opacity-0 transition-opacity group-hover:opacity-100"
                    onClick={onDownload}
                >
                    <DownloadIcon size={24} />
                </Button>
            </div>

            {/* Episode content */}
            <div className="flex flex-1 flex-col">
                <div className="mb-2 flex items-center justify-between">
                    <h3 className="font-medium">{episode.title || `Episode ${episode.episodeNum}`}</h3>
                    <span className="text-muted-foreground text-sm">{episode.duration}</span>
                </div>

                {/* We don't have descriptions in our data model, but leaving this here for future enhancement */}
                {/* <p className="text-muted-foreground text-sm line-clamp-2">
                    {episode.description || "No description available."}
                </p> */}
            </div>
        </div>
    );
}
