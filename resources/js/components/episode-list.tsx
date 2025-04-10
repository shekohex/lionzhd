import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { type SeasonsWithEpisodes } from '@/types/series';
import { motion } from 'framer-motion';
import { Download, DownloadIcon, ListChecks, ListTodo } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';

interface EpisodeListProps {
    seasonsWithEpisodes: SeasonsWithEpisodes;
    className?: string;
    onDownloadEpisode?: (index: number, episode: App.Http.Integrations.LionzTv.Responses.Episode) => void;
    onDownloadSelected?: (episodes: App.Data.SelectedEpisodeData[]) => void;
}

class SelectedEpisodes {
    #generation: number;
    #selectedEpisodes: Set<string>;

    constructor() {
        this.#selectedEpisodes = new Set();
        this.#generation = 0;
    }

    clone() {
        const newInstance = new SelectedEpisodes();
        this.#selectedEpisodes.forEach((episode) => {
            newInstance.#selectedEpisodes.add(episode);
        });
        newInstance.#generation = this.#generation + 1;
        return newInstance;
    }

    add(season: number, episodeNum: number) {
        this.#selectedEpisodes.add(`${season}-${episodeNum}`);
    }

    delete(season: number, episodeNum: number) {
        this.#selectedEpisodes.delete(`${season}-${episodeNum}`);
    }

    has(season: number, episodeNum: number): boolean {
        return this.#selectedEpisodes.has(`${season}-${episodeNum}`);
    }

    get size(): number {
        return this.#selectedEpisodes.size;
    }

    clear() {
        this.#selectedEpisodes.clear();
    }

    get generation(): number {
        return this.#generation;
    }

    getSelectedEpisodes(): App.Data.SelectedEpisodeData[] {
        const selectedEpisodes: App.Data.SelectedEpisodeData[] = [];
        this.#selectedEpisodes.forEach((episode) => {
            const [season, episodeNum] = episode.split('-').map(Number);
            selectedEpisodes.push({ season: season, episodeNum: episodeNum - 1 });
        });
        // Sort the selected episodes by season and episode number
        const sorted = selectedEpisodes.sort((a, b) => {
            if (a.season === b.season) {
                return a.episodeNum - b.episodeNum;
            }
            return a.season - b.season;
        });
        return sorted;
    }
}

export default function EpisodeList({
    seasonsWithEpisodes,
    className,
    onDownloadEpisode,
    onDownloadSelected,
}: EpisodeListProps) {
    // Get available season numbers and sort them
    const seasonNumbers = Object.keys(seasonsWithEpisodes)
        .map(Number)
        .sort((a, b) => a - b);

    const [selectedSeason, setSelectedSeason] = useState<number>(seasonNumbers.length > 0 ? seasonNumbers[0] : 0);
    const [selectedEpisodes, setSelectedEpisodes] = useState<SelectedEpisodes>(new SelectedEpisodes());

    // Get episodes for the selected season
    const episodes = useMemo(
        () => (selectedSeason ? seasonsWithEpisodes[selectedSeason] || [] : []),
        [seasonsWithEpisodes, selectedSeason],
    );

    const isAllSelected = useMemo(
        () =>
            episodes.length > 0 &&
            episodes.every((episode) => selectedEpisodes.has(selectedSeason, episode.episodeNum)),
        [episodes, selectedEpisodes, selectedSeason],
    );
    // Handle Select All button click
    const handleSelectAll = useCallback(() => {
        const allSelected = isAllSelected;
        // If all are selected, deselect all
        if (allSelected) {
            setSelectedEpisodes((prevSelectedEpisodes) => {
                const newSelectedEpisodes = prevSelectedEpisodes.clone();
                // Remove all selected episodes
                episodes.forEach((episode) => {
                    newSelectedEpisodes.delete(selectedSeason, episode.episodeNum);
                });
                return newSelectedEpisodes;
            });
        } else {
            setSelectedEpisodes((prevSelectedEpisodes) => {
                const newSelectedEpisodes = prevSelectedEpisodes.clone();
                // Select all episodes
                episodes.forEach((episode) => {
                    newSelectedEpisodes.add(selectedSeason, episode.episodeNum);
                });
                return newSelectedEpisodes;
            });
        }
    }, [episodes, selectedSeason, isAllSelected]);

    const handleEpisodeSelect = useCallback(
        (episodeNum: number, isSelected: boolean) => {
            setSelectedEpisodes((prevSelectedEpisodes) => {
                const newSelectedEpisodes = prevSelectedEpisodes.clone();
                if (isSelected) {
                    newSelectedEpisodes.add(selectedSeason, episodeNum);
                } else {
                    newSelectedEpisodes.delete(selectedSeason, episodeNum);
                }
                return newSelectedEpisodes;
            });
        },
        [selectedSeason],
    );

    // Handle download selected episodes button click
    const handleDownloadSelected = useCallback(() => {
        const selectedEpisodesArray = selectedEpisodes.getSelectedEpisodes();
        onDownloadSelected?.(selectedEpisodesArray);
    }, [onDownloadSelected, selectedEpisodes]);

    if (seasonNumbers.length === 0) return null;

    return (
        <div className={cn('relative w-full', className)}>
            <div className="mb-6 flex items-center justify-between">
                <h2 className="text-xl font-semibold">Episodes</h2>

                <div className="flex items-center gap-4">
                    {/* Download Selected button */}
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button onClick={handleDownloadSelected} disabled={selectedEpisodes.size === 0}>
                                    <Download size={20} />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p className="text-sm">
                                    {selectedEpisodes.size === 0
                                        ? 'Select Episodes to download'
                                        : `Download Selected (${selectedEpisodes.size})`}
                                </p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                    {/* Select All button */}
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button onClick={handleSelectAll}>
                                    {isAllSelected ? <ListTodo size={20} /> : <ListChecks size={20} />}
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p className="text-sm">
                                    {isAllSelected ? 'Deselect All' : `Select All (${episodes.length})`}
                                </p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                    {/* Season selector */}
                    {seasonNumbers.length > 1 && (
                        <Select
                            value={String(selectedSeason)}
                            onValueChange={(value) => setSelectedSeason(Number(value))}
                        >
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
                        <EpisodeCard
                            selected={selectedEpisodes.has(selectedSeason, episode.episodeNum)}
                            episode={episode}
                            onDownload={() => onDownloadEpisode?.(index, episode)}
                            onSelected={(isSelected) => handleEpisodeSelect(episode.episodeNum, isSelected)}
                        />
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
    selected?: boolean;
    onDownload?: () => void;
    onSelected?: (isSelected: boolean) => void;
}

function EpisodeCard({ episode, onDownload, onSelected, selected }: EpisodeCardProps) {
    // Format episode number with leading zero if needed
    const formattedEpisodeNum = episode.episodeNum < 10 ? `0${episode.episodeNum}` : String(episode.episodeNum);

    return (
        <div
            className="group hover:bg-accent/30 relative flex w-full gap-4 rounded-md border p-4 transition-all"
            onClick={() => onSelected?.(!selected)}
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
                    <div className="mb-2 flex items-center justify-between gap-4">
                        <span className="text-muted-foreground text-sm">{episode.duration}</span>
                        {/* Select Checkbox to select this episode for an action */}
                        <Checkbox
                            checked={selected}
                            onCheckedChange={(checked) => {
                                onSelected?.(checked as boolean);
                            }}
                            className="h-4 w-4"
                            onClick={(e) => {
                                e.stopPropagation();
                            }}
                        />
                    </div>
                </div>

                {/* We don't have descriptions in our data model, but leaving this here for future enhancement */}
                {/* <p className="text-muted-foreground text-sm line-clamp-2">
                    {episode.description || "No description available."}
                </p> */}
            </div>
        </div>
    );
}
