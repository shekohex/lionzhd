<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Series;
use App\Models\User;
use App\Models\VodStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CategoryFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_movies_can_be_filtered_by_category(): void
    {
        $user = User::factory()->create();

        Category::create([
            'category_id' => 1,
            'category_name' => 'Action',
            'parent_id' => 0,
            'type' => 'movie',
        ]);

        VodStream::forceCreate([
            'stream_id' => 100,
            'num' => 1,
            'name' => 'Action Movie',
            'stream_type' => 'movie',
            'category_id' => 1,
            'container_extension' => 'mp4',
            'added' => '1234567890',
        ]);

        VodStream::forceCreate([
            'stream_id' => 101,
            'num' => 2,
            'name' => 'Comedy Movie',
            'stream_type' => 'movie',
            'category_id' => 2,
            'container_extension' => 'mp4',
            'added' => '1234567890',
        ]);

        $response = $this->actingAs($user)->get(route('movies', ['category' => 1]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('movies/index')
            ->has('movies.data', 1)
            ->where('movies.data.0.name', 'Action Movie')
            ->has('categories', 1)
            ->where('categories.0.category_name', 'Action')
        );
    }

    public function test_series_can_be_filtered_by_category(): void
    {
        $user = User::factory()->create();

        Category::create([
            'category_id' => 10,
            'category_name' => 'Drama',
            'parent_id' => 0,
            'type' => 'series',
        ]);

        Series::forceCreate([
            'series_id' => 200,
            'num' => 1,
            'name' => 'Drama Series',
            'category_id' => 10,
            'last_modified' => '1234567890',
        ]);

        Series::forceCreate([
            'series_id' => 201,
            'num' => 2,
            'name' => 'SciFi Series',
            'category_id' => 11,
            'last_modified' => '1234567890',
        ]);

        $response = $this->actingAs($user)->get(route('series', ['category' => 10]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('series/index')
            ->has('series.data', 1)
            ->where('series.data.0.name', 'Drama Series')
            ->has('categories', 1)
            ->where('categories.0.category_name', 'Drama')
        );
    }
}
