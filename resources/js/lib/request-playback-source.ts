import type { VideoPlayerSource } from '@/components/video-player';

interface PlaybackLinkResponse {
    url: string;
}

export async function requestPlaybackSource(
    endpoint: string,
    source: Omit<VideoPlayerSource, 'url'>,
): Promise<VideoPlayerSource> {
    const response = await window.fetch(endpoint, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error(`Playback link request failed with status ${response.status}.`);
    }

    const data = (await response.json()) as PlaybackLinkResponse;

    if (typeof data.url !== 'string' || data.url === '') {
        throw new Error('Playback link response did not contain a URL.');
    }

    return { ...source, url: data.url };
}
