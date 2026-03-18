<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

if (! extension_loaded('sockets')) {
    it('requires the sockets extension for browser sidebar tests', function (): void {
        expect(true)->toBeTrue();
    })->group('browser')->skip('ext-sockets is required by pest-plugin-browser.');
} else {
    it('matches the desktop movie category sidebar height to the main content section and scrolls long category lists', function (): void {
        $user = User::factory()->create();

        seedMovieSidebarOverflowFixture();

        test()->actingAs($user);

        $metrics = visit(route('movies'))
            ->waitForText('Movie Categories')
            ->assertNoJavaScriptErrors()
            ->script(<<<'JS'
                () => {
                    const viewport = document.querySelector('aside [data-slot="scroll-area-viewport"]');

                    if (!viewport) {
                        return { found: false };
                    }

                    const shell = document.querySelector('aside > div');
                    const row = shell?.closest('aside')?.parentElement?.parentElement;
                    const section = row?.querySelector('section');
                    const lastCategory = Array.from(document.querySelectorAll('aside button')).find((button) =>
                        button.textContent?.includes('Movie Category 120')
                    );

                    if (!shell || !section || !lastCategory) {
                        return {
                            found: true,
                            shellFound: Boolean(shell),
                            sectionFound: Boolean(section),
                            lastCategoryFound: Boolean(lastCategory),
                        };
                    }

                    const before = viewport.scrollTop;
                    viewport.scrollTop = viewport.scrollHeight;
                    const after = viewport.scrollTop;

                    return {
                        found: true,
                        shellFound: true,
                        sectionFound: true,
                        lastCategoryFound: true,
                        viewportWidth: window.innerWidth,
                        windowHeight: window.innerHeight,
                        shellHeight: shell.getBoundingClientRect().height,
                        sectionHeight: section.getBoundingClientRect().height,
                        clientHeight: viewport.clientHeight,
                        scrollHeight: viewport.scrollHeight,
                        before,
                        after,
                    };
                }
            JS);

        expect($metrics['found'])->toBeTrue();
        expect($metrics['shellFound'])->toBeTrue();
        expect($metrics['sectionFound'])->toBeTrue();
        expect($metrics['lastCategoryFound'])->toBeTrue();
        expect($metrics['viewportWidth'])->toBeGreaterThan(1024);
        expect(abs($metrics['shellHeight'] - $metrics['sectionHeight']))->toBeLessThan(4);
        expect($metrics['scrollHeight'])->toBeGreaterThan($metrics['clientHeight']);
        expect($metrics['after'])->toBeGreaterThan($metrics['before']);
    })->group('browser');

    it('matches the desktop series category sidebar height to the main content section and scrolls long category lists', function (): void {
        $user = User::factory()->create();

        seedSeriesSidebarOverflowFixture();

        test()->actingAs($user);

        $metrics = visit(route('series'))
            ->waitForText('Series Categories')
            ->assertNoJavaScriptErrors()
            ->script(<<<'JS'
                () => {
                    const viewport = document.querySelector('aside [data-slot="scroll-area-viewport"]');

                    if (!viewport) {
                        return { found: false };
                    }

                    const shell = document.querySelector('aside > div');
                    const row = shell?.closest('aside')?.parentElement?.parentElement;
                    const section = row?.querySelector('section');
                    const lastCategory = Array.from(document.querySelectorAll('aside button')).find((button) =>
                        button.textContent?.includes('Series Category 120')
                    );

                    if (!shell || !section || !lastCategory) {
                        return {
                            found: true,
                            shellFound: Boolean(shell),
                            sectionFound: Boolean(section),
                            lastCategoryFound: Boolean(lastCategory),
                        };
                    }

                    const before = viewport.scrollTop;
                    viewport.scrollTop = viewport.scrollHeight;
                    const after = viewport.scrollTop;

                    return {
                        found: true,
                        shellFound: true,
                        sectionFound: true,
                        lastCategoryFound: true,
                        viewportWidth: window.innerWidth,
                        windowHeight: window.innerHeight,
                        shellHeight: shell.getBoundingClientRect().height,
                        sectionHeight: section.getBoundingClientRect().height,
                        clientHeight: viewport.clientHeight,
                        scrollHeight: viewport.scrollHeight,
                        before,
                        after,
                    };
                }
            JS);

        expect($metrics['found'])->toBeTrue();
        expect($metrics['shellFound'])->toBeTrue();
        expect($metrics['sectionFound'])->toBeTrue();
        expect($metrics['lastCategoryFound'])->toBeTrue();
        expect($metrics['viewportWidth'])->toBeGreaterThan(1024);
        expect(abs($metrics['shellHeight'] - $metrics['sectionHeight']))->toBeLessThan(4);
        expect($metrics['scrollHeight'])->toBeGreaterThan($metrics['clientHeight']);
        expect($metrics['after'])->toBeGreaterThan($metrics['before']);
    })->group('browser');
}

function seedMovieSidebarOverflowFixture(): void
{
    foreach (range(1, 120) as $index) {
        $categoryId = sprintf('movie-browser-%02d', $index);

        Category::query()->create([
            'provider_id' => $categoryId,
            'name' => sprintf('Movie Category %02d with a much longer label for desktop overflow coverage', $index),
            'in_vod' => true,
            'in_series' => false,
            'is_system' => false,
        ]);

        if ($index <= 12) {
            VodStream::withoutSyncingToSearch(static function () use ($index, $categoryId): void {
                VodStream::unguarded(static function () use ($index, $categoryId): void {
                    VodStream::query()->create([
                        'stream_id' => 30_000 + $index,
                        'num' => 30_000 + $index,
                        'name' => sprintf('Browser Movie %02d', $index),
                        'stream_type' => 'movie',
                        'added' => now()->toIso8601String(),
                        'category_id' => $categoryId,
                        'container_extension' => 'mp4',
                    ]);
                });
            });
        }
    }
}

function seedSeriesSidebarOverflowFixture(): void
{
    foreach (range(1, 120) as $index) {
        $categoryId = sprintf('series-browser-%02d', $index);

        Category::query()->create([
            'provider_id' => $categoryId,
            'name' => sprintf('Series Category %02d with a much longer label for desktop overflow coverage', $index),
            'in_vod' => false,
            'in_series' => true,
            'is_system' => false,
        ]);

        if ($index <= 12) {
            DB::table('series')->insert([
                'series_id' => 40_000 + $index,
                'num' => 40_000 + $index,
                'name' => sprintf('Browser Series %02d', $index),
                'category_id' => $categoryId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
