<?php

declare(strict_types=1);

use App\Models\MediaDownloadRef;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('features.direct_download_links', true);
});

it('forbids external members from movie server downloads', function (): void {
    $user = User::factory()->memberExternal()->create();

    DB::table('vod_streams')->insert([
        'stream_id' => 1001,
        'num' => 1001,
        'name' => 'Test Movie',
        'stream_type' => 'movie',
        'stream_icon' => null,
        'rating' => null,
        'rating_5based' => 0,
        'added' => now()->toDateTimeString(),
        'is_adult' => false,
        'category_id' => null,
        'container_extension' => 'mp4',
        'custom_sid' => null,
        'direct_source' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('movies.download', ['model' => 1001]));

    $response->assertForbidden();
});

it('forbids external members from download operations', function (string $method, string $routeName): void {
    $user = User::factory()->memberExternal()->create();
    $download = MediaDownloadRef::query()->create([
        'gid' => 'external-gid-1',
        'media_id' => 1001,
        'media_type' => VodStream::class,
        'downloadable_id' => 1001,
    ]);

    $response = $this->actingAs($user)->{$method}(route($routeName, ['model' => $download->id]));

    $response->assertForbidden();
})->with([
    ['patch', 'downloads.edit'],
    ['delete', 'downloads.destroy'],
]);

it('forbids internal members from download operations', function (string $method, string $routeName): void {
    $user = User::factory()->memberInternal()->create();
    $download = MediaDownloadRef::query()->create([
        'gid' => 'internal-gid-1',
        'media_id' => 1001,
        'media_type' => VodStream::class,
        'downloadable_id' => 1001,
    ]);

    $response = $this->actingAs($user)->{$method}(route($routeName, ['model' => $download->id]));

    $response->assertForbidden();
})->with([
    ['patch', 'downloads.edit'],
    ['delete', 'downloads.destroy'],
]);

it('allows signed direct download resolution and redirects', function (): void {
    $token = 'direct-token-1';
    $remoteUrl = 'https://example.com/direct.mp4';

    Cache::put("direct:link:{$token}", $remoteUrl, now()->addMinutes(30));

    $signedUrl = URL::temporarySignedRoute('direct.resolve', now()->addMinutes(30), ['token' => $token]);

    $response = $this->get($signedUrl);

    $response->assertRedirect($remoteUrl);
});
