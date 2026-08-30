<?php

declare(strict_types=1);

use App\Http\Integrations\LionzTv\Requests\GetSeriesInfoRequest;
use App\Http\Integrations\LionzTv\Requests\GetVodInfoRequest;
use App\Http\Integrations\LionzTv\XtreamCodesConnector;
use App\Models\Series;
use App\Models\User;
use App\Models\VodStream;
use App\Models\XtreamCodesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

describe('Playback Controller', function (): void {
    beforeEach(function (): void {
        app()->bind(XtreamCodesConfig::class, static fn () => new XtreamCodesConfig([
            'host' => 'provider.example',
            'port' => 8080,
            'username' => 'viewer',
            'password' => 'secret',
        ]));
    });

    it('issues a signed movie capability and redirects it to the generated provider URL', function (): void {
        $user = User::factory()->create();
        $movie = playbackCreateMovie(501);
        playbackBindMovieInfo(501);

        $response = $this->actingAs($user)->getJson(route('movies.playback', ['model' => $movie]));

        $response->assertOk()->assertJsonStructure(['url']);
        $signedUrl = $response->json('url');
        expect($signedUrl)->toBeString()->toContain('/watch/movie/')->toContain('signature=');

        $token = basename((string) parse_url($signedUrl, PHP_URL_PATH));
        $providerUrl = Cache::get("playback:link:movie:{$token}");

        expect($providerUrl)->toBe('http://provider.example:8080/movie/viewer/secret/501.mp4');
        $this->get($signedUrl)->assertRedirect($providerUrl);
    });

    it('issues a signed episode capability and redirects it to the generated provider URL', function (): void {
        $user = User::factory()->create();
        $series = playbackCreateSeries(601);
        playbackBindSeriesInfo('902');

        $response = $this->actingAs($user)->getJson(route('series.playback.single', [
            'model' => $series,
            'season' => 1,
            'episode' => 0,
        ]));

        $response->assertOk()->assertJsonStructure(['url']);
        $signedUrl = $response->json('url');
        expect($signedUrl)->toBeString()->toContain('/watch/episode/')->toContain('signature=');

        $token = basename((string) parse_url($signedUrl, PHP_URL_PATH));
        $providerUrl = Cache::get("playback:link:episode:{$token}");

        expect($providerUrl)->toBe('http://provider.example:8080/series/viewer/secret/902.m3u8');
        $this->get($signedUrl)->assertRedirect($providerUrl);
    });

    it('redirects a valid movie playback request directly to the provider', function (): void {
        $providerUrl = 'https://provider.example/movie/user/pass/501.mp4';
        Cache::put('playback:link:movie:movie-token', $providerUrl, now()->addMinutes(30));

        $url = URL::temporarySignedRoute('playback.resolve', now()->addMinutes(30), [
            'mediaType' => 'movie',
            'token' => 'movie-token',
        ]);

        $response = $this->get($url);

        $response->assertStatus(302);
        $response->assertRedirect($providerUrl);
        $response->assertHeaderMissing('Content-Range');
        $response->assertHeaderMissing('Accept-Ranges');
        $response->assertHeader('Content-Type', 'text/html; charset=utf-8');
        expect($response->getContent())->not->toContain('#EXTM3U');
    });

    it('redirects a valid episode playback request directly to the provider', function (): void {
        $providerUrl = 'https://provider.example/series/user/pass/902.m3u8';
        Cache::put('playback:link:episode:episode-token', $providerUrl, now()->addMinutes(30));

        $url = URL::temporarySignedRoute('playback.resolve', now()->addMinutes(30), [
            'mediaType' => 'episode',
            'token' => 'episode-token',
        ]);

        $response = $this->get($url);

        $response->assertStatus(302);
        $response->assertRedirect($providerUrl);
    });

    it('rejects an unsigned playback request', function (): void {
        $this->get('/watch/movie/movie-token')->assertForbidden();
    });

    it('rejects an expired playback signature', function (): void {
        $url = URL::temporarySignedRoute('playback.resolve', now()->subMinute(), [
            'mediaType' => 'episode',
            'token' => 'expired-token',
        ]);

        $this->get($url)->assertForbidden();
    });

    it('returns not found after the cached playback capability expires', function (): void {
        $url = URL::temporarySignedRoute('playback.resolve', now()->addMinutes(30), [
            'mediaType' => 'movie',
            'token' => 'missing-token',
        ]);

        $this->get($url)->assertNotFound();
    });

    it('requires authentication to obtain playback links', function (): void {
        $this->get('/movies/1/playback')->assertRedirect(route('login'));
        $this->get('/series/1/1/0/playback')->assertRedirect(route('login'));
    });
});

function playbackCreateMovie(int $streamId): VodStream
{
    $movie = null;

    VodStream::withoutSyncingToSearch(static function () use ($streamId, &$movie): void {
        VodStream::unguarded(static function () use ($streamId, &$movie): void {
            $movie = VodStream::query()->create([
                'stream_id' => $streamId,
                'num' => $streamId,
                'name' => 'Playback Movie',
                'stream_type' => 'movie',
                'added' => now()->toIso8601String(),
                'container_extension' => 'mp4',
            ]);
        });
    });

    if (! $movie instanceof VodStream) {
        throw new RuntimeException('Failed to create movie playback fixture.');
    }

    return $movie;
}

function playbackCreateSeries(int $seriesId): Series
{
    DB::table('series')->insert([
        'series_id' => $seriesId,
        'num' => $seriesId,
        'name' => 'Playback Series',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Series::query()->findOrFail($seriesId);
}

function playbackBindMovieInfo(int $streamId): void
{
    $mockClient = new MockClient([
        GetVodInfoRequest::class => MockResponse::make([
            'info' => [],
            'movie_data' => [
                'stream_id' => $streamId,
                'name' => 'Playback Movie',
                'container_extension' => 'mp4',
            ],
        ], 200),
    ]);

    playbackBindXtreamClient($mockClient);
}

function playbackBindSeriesInfo(string $episodeId): void
{
    $mockClient = new MockClient([
        GetSeriesInfoRequest::class => MockResponse::make([
            'info' => [
                'name' => 'Playback Series',
                'cover' => '',
                'plot' => '',
                'cast' => '',
                'director' => '',
                'genre' => '',
                'releaseDate' => '',
                'last_modified' => '',
                'rating' => '0',
                'rating_5based' => 0,
                'backdrop_path' => [],
                'youtube_trailer' => '',
                'episode_run_time' => '',
                'category_id' => '',
            ],
            'seasons' => ['1'],
            'episodes' => [
                '1' => [[
                    'id' => $episodeId,
                    'episode_num' => 1,
                    'title' => 'Episode 1',
                    'container_extension' => 'm3u8',
                    'season' => 1,
                    'info' => [],
                ]],
            ],
        ], 200),
    ]);

    playbackBindXtreamClient($mockClient);
}

function playbackBindXtreamClient(MockClient $mockClient): void
{
    app()->bind(XtreamCodesConnector::class, static function () use ($mockClient): XtreamCodesConnector {
        $connector = new XtreamCodesConnector(app(XtreamCodesConfig::class));

        return $connector->withMockClient($mockClient);
    });
}
